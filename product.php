<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
ensure_size_schema();
ensure_review_schema();
$stmt = db()->prepare('SELECT p.*, c.name category_name, COALESCE(i.stock,0) stock FROM products p JOIN categories c ON c.id=p.category_id LEFT JOIN inventory i ON i.product_id=p.id WHERE p.slug=? AND p.status="active"');
$stmt->execute([$_GET['slug'] ?? '']);
$p = $stmt->fetch();
if (!$p) { http_response_code(404); exit('Product not found'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!current_user()) {
        $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'] ?? app_url('product.php?slug=' . urlencode($p['slug']));
        flash('error', 'Vui lòng đăng nhập để thêm sản phẩm vào giỏ hàng.');
        header('Location: auth/login.php');
        exit;
    }
    $size = trim((string)($_POST['size'] ?? ''));
    if ($size === '') {
        flash('error', 'Vui lòng chọn size giày trước khi thêm vào giỏ hàng.');
        header('Location: product.php?slug=' . urlencode($p['slug']));
        exit;
    }
    $sizeStmt = db()->prepare('SELECT stock FROM product_sizes WHERE product_id=? AND size=?');
    $sizeStmt->execute([(int)$p['id'], $size]);
    $sizeStock = $sizeStmt->fetchColumn();
    if ($sizeStock === false || (int)$sizeStock < 1) {
        flash('error', 'Size đã chọn đã hết hàng.');
        header('Location: product.php?slug=' . urlencode($p['slug']));
        exit;
    }
    $qty = min((int)$sizeStock, max(1, (int)($_POST['quantity'] ?? 1)));
    $key = (int)$p['id'] . ':' . $size;
    $_SESSION['cart'][$key] = [
        'product_id' => (int)$p['id'],
        'size' => $size,
        'quantity' => min((int)$sizeStock, (int)($_SESSION['cart'][$key]['quantity'] ?? 0) + $qty),
        'unit_price' => (float)($p['sale_price'] ?: $p['price']),
    ];
    flash('success', 'Đã thêm vào giỏ hàng.');
    header('Location: cart.php'); exit;
}
$sizeStmt = db()->prepare('SELECT * FROM product_sizes WHERE product_id=? ORDER BY CAST(size AS UNSIGNED), size');
$sizeStmt->execute([(int)$p['id']]);
$sizes = $sizeStmt->fetchAll();
$reviewsStmt = db()->prepare('SELECT r.*, u.name FROM reviews r JOIN users u ON u.id=r.user_id WHERE product_id=? AND approved=1 ORDER BY r.created_at DESC');
$reviewsStmt->execute([$p['id']]);
$reviews = $reviewsStmt->fetchAll();
$reviewStats = ['count' => count($reviews), 'avg' => 0, 'dist' => [1=>0,2=>0,3=>0,4=>0,5=>0]];
foreach ($reviews as $r) { $reviewStats['avg'] += (int)$r['rating']; $reviewStats['dist'][(int)$r['rating']]++; }
if ($reviewStats['count']) $reviewStats['avg'] = round($reviewStats['avg'] / $reviewStats['count'], 1);
$mediaByReview = [];
foreach ($reviews as $r) {
    if (!empty($r['image'])) {
        $mediaByReview[(int)$r['id']][] = ['file_path' => $r['image'], 'file_type' => 'image', 'mime_type' => 'image/jpeg'];
    }
}
if ($reviews) {
    $ids = array_column($reviews, 'id');
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $mediaStmt = db()->prepare("SELECT * FROM review_media WHERE review_id IN ($ph) ORDER BY id");
    $mediaStmt->execute($ids);
    foreach ($mediaStmt as $m) $mediaByReview[(int)$m['review_id']][] = $m;
}
$couponStmt = db()->prepare("SELECT DISTINCT c.* FROM coupons c LEFT JOIN coupon_products cp ON cp.coupon_id=c.id LEFT JOIN coupon_categories cc ON cc.coupon_id=c.id WHERE c.active=1 AND (c.starts_at IS NULL OR c.starts_at<=NOW()) AND (c.ends_at IS NULL OR c.ends_at>=NOW()) AND ((cp.product_id IS NULL AND cc.category_id IS NULL) OR cp.product_id=? OR cc.category_id=?) ORDER BY c.value DESC");
$couponStmt->execute([(int)$p['id'], (int)$p['category_id']]);
$availableCoupons = $couponStmt->fetchAll();
render_header($p['name']);
?>
<main class="container py-5">
  <div class="row g-5">
    <div class="col-md-6"><img class="img-fluid rounded" src="<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>"></div>
    <div class="col-md-6">
      <p class="text-muted"><?= e($p['category_name']) ?> · <?= e($p['brand']) ?></p><h1><?= e($p['name']) ?></h1>
      <div class="text-warning mb-2">★★★★★ <span class="text-muted">(4.8/5)</span></div>
      <p><?= e($p['description']) ?></p><p class="price fs-3"><?= money($p['sale_price'] ?: $p['price']) ?></p>
      <p>Tồn kho: <?= (int)$p['stock'] ?></p>
      <div class="alert alert-light border">
        <i class="fa-solid fa-ticket me-2"></i>Coupon khả dụng:
        <?php foreach($availableCoupons as $c): ?><button type="button" class="btn btn-sm btn-outline-danger ms-1 product-coupon-pick" data-code="<?= e($c['code']) ?>"><?= e($c['code']) ?></button><?php endforeach; ?>
        <?php if(!$availableCoupons): ?><span class="text-muted">Chưa có coupon áp dụng.</span><?php endif; ?>
        <div class="row g-2 mt-2">
          <div class="col-md-7"><input id="productCoupon" class="form-control form-control-sm" placeholder="Nhập coupon" data-subtotal="<?= e((string)($p['sale_price'] ?: $p['price'])) ?>" data-product="<?= (int)$p['id'] ?>"></div>
          <div class="col-md-5 small" id="productCouponResult"></div>
        </div>
      </div>
      <form method="post" id="addToCartForm">
        <?= csrf_field() ?>
        <label class="form-label fw-semibold">Size</label>
        <div class="d-flex flex-wrap gap-2 mb-3">
          <?php foreach($sizes as $s): ?>
            <input class="btn-check" type="radio" name="size" id="size<?= (int)$s['id'] ?>" value="<?= e($s['size']) ?>" <?= (int)$s['stock']<1?'disabled':'' ?>>
            <label class="btn btn-outline-dark size-pill" for="size<?= (int)$s['id'] ?>"><?= e($s['size']) ?><small class="d-block">Còn <?= (int)$s['stock'] ?></small></label>
          <?php endforeach; ?>
        </div>
        <div class="d-flex gap-2"><input type="number" name="quantity" min="1" max="<?= (int)$p['stock'] ?>" value="1" class="form-control" style="max-width:120px"><button class="btn btn-dark" <?= $p['stock']<1?'disabled':'' ?>>Thêm vào giỏ</button></div>
      </form>
    </div>
  </div>
  <hr>
  <section class="review-panel table-card">
    <div class="d-flex flex-column flex-lg-row gap-4">
      <div style="min-width:220px"><h2 class="h4">Đánh giá sản phẩm</h2><div class="display-5 fw-bold"><?= e((string)$reviewStats['avg']) ?>/5</div><div class="text-warning fs-5"><?= str_repeat('★', (int)round($reviewStats['avg'])) ?></div><p class="text-muted"><?= (int)$reviewStats['count'] ?> đánh giá</p></div>
      <div class="flex-grow-1">
        <?php for($star=5;$star>=1;$star--): $pct=$reviewStats['count']?($reviewStats['dist'][$star]/$reviewStats['count']*100):0; ?>
          <div class="d-flex align-items-center gap-2 mb-2"><span style="width:48px"><?= $star ?> sao</span><div class="progress flex-grow-1" style="height:9px"><div class="progress-bar bg-warning" style="width:<?= (int)$pct ?>%"></div></div><span class="small text-muted"><?= (int)$reviewStats['dist'][$star] ?></span></div>
        <?php endfor; ?>
      </div>
    </div>
    <div class="row g-3 mt-3">
      <?php if(!$reviews): ?><div class="col-12"><div class="alert alert-light border text-center py-5"><h3 class="h5">Chưa có đánh giá nào cho sản phẩm này.</h3><p class="mb-0 text-muted">Hãy là người đầu tiên chia sẻ trải nghiệm của bạn.</p></div></div><?php endif; ?>
      <?php foreach ($reviews as $r): ?>
        <div class="col-md-6"><article class="review-card table-card h-100"><div class="d-flex gap-3 align-items-center mb-2"><img class="review-avatar" src="https://ui-avatars.com/api/?name=<?= urlencode($r['name']) ?>" alt=""><div><strong><?= e($r['name']) ?></strong><div class="text-warning"><?= str_repeat('★',(int)$r['rating']) ?></div><small class="text-muted"><?= e(date('d/m/Y', strtotime($r['created_at']))) ?></small></div></div><p><?= e($r['comment']) ?></p><div class="review-gallery d-flex flex-wrap gap-2"><?php foreach($mediaByReview[(int)$r['id']] ?? [] as $m): $mediaUrl=review_media_url($m['file_path']); ?><?php if($m['file_type']==='image'): ?><a href="<?= e($mediaUrl) ?>" class="review-lightbox"><img src="<?= e($mediaUrl) ?>" style="width:86px;height:86px;object-fit:cover;border-radius:8px" alt="Ảnh đánh giá"></a><?php else: ?><video controls preload="metadata" style="width:180px;max-width:100%;border-radius:8px"><source src="<?= e($mediaUrl) ?>" type="<?= e($m['mime_type']) ?>"></video><?php endif; ?><?php endforeach; ?></div></article></div>
      <?php endforeach; ?>
    </div>
  </section>
</main>
<script>
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('addToCartForm')?.addEventListener('submit', e => {
    if (!document.querySelector('input[name="size"]:checked')) {
      e.preventDefault();
      Swal.fire('Thiếu size', 'Vui lòng chọn size giày trước khi thêm vào giỏ hàng.', 'warning');
    }
  });
  const input = document.getElementById('productCoupon');
  const result = document.getElementById('productCouponResult');
  if (!input) return;
  const check = code => {
    input.value = code;
    if (!code) { result.textContent = ''; return; }
    result.className = 'col-md-5 small text-muted';
    result.textContent = 'Đang kiểm tra...';
    const body = new URLSearchParams();
    body.set('code', code);
    body.set('subtotal', input.dataset.subtotal);
    body.append('product_ids[]', input.dataset.product);
    fetch('api/coupon.php', {method:'POST', body}).then(r => r.json()).then(res => {
      result.className = 'col-md-5 small ' + (res.valid ? 'text-success' : 'text-danger');
      result.textContent = res.valid ? ('Sau giảm: ' + res.final_label) : res.message;
    }).catch(() => { result.className='col-md-5 small text-danger'; result.textContent='Không kiểm tra được coupon.'; });
  };
  input.addEventListener('input', () => check(input.value.trim()));
  document.querySelectorAll('.product-coupon-pick').forEach(btn => btn.addEventListener('click', () => check(btn.dataset.code)));
});
</script>
<?php render_footer(); ?>
