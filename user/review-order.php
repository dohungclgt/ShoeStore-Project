<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$user = require_login();
ensure_review_schema();
$orderId = (int)($_GET['order_id'] ?? $_POST['order_id'] ?? 0);
$stmt = db()->prepare("SELECT * FROM orders WHERE id=? AND user_id=? AND status IN ('delivered','completed','da_giao','hoan_thanh')");
$stmt->execute([$orderId, $user['id']]);
$order = $stmt->fetch();
if (!$order) { flash('error','Bạn chỉ có thể đánh giá sản phẩm trong đơn đã giao hoặc hoàn thành.'); header('Location: orders.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $productId = (int)$_POST['product_id'];
    $exists = db()->prepare('SELECT COUNT(*) FROM order_items WHERE order_id=? AND product_id=?');
    $exists->execute([$orderId,$productId]);
    $dup = db()->prepare('SELECT COUNT(*) FROM reviews WHERE user_id=? AND order_id=? AND product_id=?');
    $dup->execute([$user['id'],$orderId,$productId]);
    if (!$exists->fetchColumn()) flash('error','Sản phẩm không thuộc đơn hàng này.');
    elseif ($dup->fetchColumn()) flash('error','Bạn đã đánh giá sản phẩm này trong đơn hàng.');
    else {
        try {
            $media = isset($_FILES['media']) ? upload_review_media_files($_FILES['media']) : [];
            db()->beginTransaction();
            db()->prepare('INSERT INTO reviews(user_id,product_id,order_id,rating,comment,approved) VALUES(?,?,?,?,?,1)')->execute([$user['id'],$productId,$orderId,(int)$_POST['rating'],trim($_POST['comment'])]);
            $reviewId = (int)db()->lastInsertId();
            $mediaStmt = db()->prepare('INSERT INTO review_media(review_id,file_path,file_type,mime_type) VALUES(?,?,?,?)');
            foreach ($media as $m) $mediaStmt->execute([$reviewId,$m['path'],$m['type'],$m['mime']]);
            audit_log('create_review','reviews',$reviewId,['product_id'=>$productId,'media_count'=>count($media)]);
            db()->commit();
            $productLink = 'admin/reviews/index.php';
            $productStmt = db()->prepare('SELECT slug FROM products WHERE id=?');
            $productStmt->execute([$productId]);
            $productSlug = (string)$productStmt->fetchColumn();
            if ($productSlug !== '') {
                $productLink = 'product.php?slug=' . rawurlencode($productSlug);
            }
            notify_admins('Có đánh giá mới','Một đánh giá sản phẩm mới đã được đăng.', 'review', $productLink);
            flash('success','Đánh giá đã được đăng và hiển thị ngay.');
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            flash('error', $e->getMessage());
        }
    }
    header('Location: review-order.php?order_id='.$orderId); exit;
}
$items = db()->prepare('SELECT oi.*,p.image FROM order_items oi JOIN products p ON p.id=oi.product_id WHERE oi.order_id=?');
$items->execute([$orderId]);
render_header('Đánh giá đơn hàng');
?>
<main class="container py-5"><h1 class="section-title">Đánh giá đơn hàng <?= e($order['code']) ?></h1><div class="row g-3"><?php foreach($items as $item): ?><div class="col-md-6"><form method="post" enctype="multipart/form-data" class="table-card" data-confirm-submit="Gửi đánh giá sản phẩm này?"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int)$orderId ?>"><input type="hidden" name="product_id" value="<?= (int)$item['product_id'] ?>"><div class="d-flex gap-3 mb-3"><img src="<?= e($item['image']) ?>" style="width:82px;height:82px;object-fit:cover;border-radius:6px" alt=""><div><h2 class="h6"><?= e($item['product_name']) ?></h2><small class="text-muted">Size: <?= e($item['size'] ?? '') ?> · Số lượng: <?= (int)$item['quantity'] ?></small></div></div><label>Số sao</label><select name="rating" class="form-select mb-2"><option value="5">5 sao</option><option value="4">4 sao</option><option value="3">3 sao</option><option value="2">2 sao</option><option value="1">1 sao</option></select><label>Nội dung đánh giá</label><textarea name="comment" class="form-control mb-2" required></textarea><label>Ảnh/video đánh giá</label><input type="file" name="media[]" class="form-control mb-2" multiple accept=".jpg,.jpeg,.png,.webp,.gif,.mp4,.webm,.mov,image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime"><small class="text-muted d-block mb-3">Chỉ hỗ trợ ảnh jpg, png, webp, gif và video mp4, webm, mov.</small><button class="btn btn-dark">Gửi đánh giá</button></form></div><?php endforeach; ?></div></main>
<?php render_footer(); ?>
