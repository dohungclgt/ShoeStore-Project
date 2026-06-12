<?php
require_once __DIR__ . '/../_admin.php';
require_role(['Super Admin','Admin','Staff']);
ensure_review_schema();
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'show') db()->prepare('UPDATE reviews SET approved=1 WHERE id=?')->execute([$_POST['id']]);
    if ($action === 'hide') db()->prepare('UPDATE reviews SET approved=0 WHERE id=?')->execute([$_POST['id']]);
    if ($action === 'delete') db()->prepare('DELETE FROM reviews WHERE id=?')->execute([$_POST['id']]);
    audit_log('moderate_review','reviews',(int)$_POST['id'],['action'=>$action]);
    flash('success','Đã cập nhật đánh giá.');
    header('Location: index.php'); exit;
}
$rows = db()->query('SELECT r.*,u.name user_name,p.name product_name,o.code order_code FROM reviews r JOIN users u ON u.id=r.user_id JOIN products p ON p.id=r.product_id LEFT JOIN orders o ON o.id=r.order_id ORDER BY r.created_at DESC')->fetchAll();
$mediaByReview = [];
foreach ($rows as $r) {
    if (!empty($r['image'])) {
        $mediaByReview[(int)$r['id']][] = ['file_path' => $r['image'], 'file_type' => 'image', 'mime_type' => 'image/jpeg'];
    }
}
if ($rows) {
    $ids = array_column($rows, 'id');
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare("SELECT * FROM review_media WHERE review_id IN ($ph)");
    $stmt->execute($ids);
    foreach ($stmt as $m) $mediaByReview[(int)$m['review_id']][] = $m;
}
admin_boot('reviews','Quản lý đánh giá');
?>
<h1 class="section-title">Quản lý đánh giá</h1>
<div class="table-card"><table class="table datatable align-middle"><thead><tr><th>Khách</th><th>Sản phẩm</th><th>Đơn</th><th>Sao</th><th>Nội dung</th><th>Media</th><th>Trạng thái</th><th></th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= e($r['user_name']) ?></td><td><?= e($r['product_name']) ?></td><td><?= e($r['order_code'] ?? '') ?></td><td><?= (int)$r['rating'] ?></td><td><?= e($r['comment']) ?></td><td><?php foreach($mediaByReview[(int)$r['id']] ?? [] as $m): $mediaUrl=review_media_url($m['file_path']); ?><?php if($m['file_type']==='image'): ?><a class="review-lightbox" href="<?= e($mediaUrl) ?>"><img src="<?= e($mediaUrl) ?>" style="width:54px;height:54px;object-fit:cover;border-radius:6px" alt="Ảnh đánh giá"></a><?php else: ?><video controls preload="metadata" style="width:100px;max-height:70px"><source src="<?= e($mediaUrl) ?>" type="<?= e($m['mime_type']) ?>"></video><?php endif; ?><?php endforeach; ?></td><td><?= $r['approved']?'Đang hiện':'Đã ẩn' ?></td><td><form method="post" class="d-flex gap-1"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><?php if($r['approved']): ?><button name="action" value="hide" class="btn btn-sm btn-outline-warning">Ẩn</button><?php else: ?><button name="action" value="show" class="btn btn-sm btn-dark">Hiện</button><?php endif; ?><button name="action" value="delete" class="btn btn-sm btn-outline-danger" data-confirm-submit="Xóa đánh giá này?">Xóa</button></form></td></tr><?php endforeach; ?></tbody></table></div>
<?php admin_end(); ?>
