<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
ensure_support_schema();
$q = trim((string)($_GET['q'] ?? ''));
$where = "WHERE status='active' OR active=1";
$params = [];
if ($q !== '') {
    $where = "WHERE (status='active' OR active=1) AND (title LIKE ? OR content LIKE ? OR excerpt LIKE ?)";
    $like = "%$q%";
    $params = [$like, $like, $like];
}
$stmt = db()->prepare("SELECT title,slug,excerpt,content,updated_at FROM policies $where ORDER BY title");
$stmt->execute($params);
$rows = $stmt->fetchAll();
$icons = ['mua'=>'fa-bag-shopping','thanh'=>'fa-credit-card','giao'=>'fa-truck','huy'=>'fa-ban','doi'=>'fa-right-left','tra'=>'fa-rotate-left','hoan'=>'fa-money-bill-transfer','bao'=>'fa-shield-halved','cod'=>'fa-hand-holding-dollar','vnpay'=>'fa-building-columns','khieu'=>'fa-comments','ho tro'=>'fa-headset'];
render_header('Chính sách cửa hàng');
?>
<main class="container py-5">
  <div class="d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-end mb-4">
    <div><h1 class="section-title mb-1">Chính sách cửa hàng</h1><p class="section-subtitle mb-0">Tra cứu quy định mua hàng, thanh toán, giao nhận, đổi trả và hỗ trợ tại ShoeStore.</p></div>
    <a class="btn btn-dark" href="<?= e(app_url('user/tickets.php')) ?>"><i class="fa-solid fa-headset me-1"></i>Tạo ticket hỗ trợ</a>
  </div>
  <form class="table-card mb-4" method="get">
    <div class="input-group"><span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Tìm chính sách thanh toán, hoàn tiền, bảo hành..."><button class="btn btn-dark">Tìm</button></div>
  </form>
  <div class="row g-3">
    <?php foreach ($rows as $p): $slug = $p['slug']; $icon='fa-file-contract'; foreach($icons as $key=>$ic){ if(str_contains($slug,$key)){ $icon=$ic; break; } } ?>
      <div class="col-md-6 col-xl-4">
        <article class="table-card h-100 policy-card">
          <div class="policy-icon"><i class="fa-solid <?= e($icon) ?>"></i></div>
          <h2 class="h5"><?= e($p['title']) ?></h2>
          <p class="text-muted"><?= e($p['excerpt'] ?: mb_strimwidth(strip_tags($p['content']), 0, 180, '...', 'UTF-8')) ?></p>
          <a class="btn btn-outline-dark btn-sm" href="<?= e(app_url('policy-detail.php?slug=' . urlencode($p['slug']))) ?>">Xem chi tiết</a>
        </article>
      </div>
    <?php endforeach; ?>
    <?php if (!$rows): ?><div class="col-12"><div class="alert alert-info">Không tìm thấy chính sách phù hợp.</div></div><?php endif; ?>
  </div>
</main>
<?php render_footer(); ?>
