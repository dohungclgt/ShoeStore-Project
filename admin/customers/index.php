<?php
require_once __DIR__ . '/../_admin.php';
if($_SERVER['REQUEST_METHOD']==='POST'){ verify_csrf(); db()->prepare('UPDATE users SET status=? WHERE id=?')->execute([$_POST['status'],$_POST['id']]); flash('success','Đã cập nhật khách hàng.'); header('Location: index.php'); exit; }
$rows=db()->query("SELECT u.*,r.name role_name,(SELECT COUNT(*) FROM orders o WHERE o.user_id=u.id) order_count FROM users u JOIN roles r ON r.id=u.role_id WHERE r.name='Customer' ORDER BY u.created_at DESC")->fetchAll();
admin_boot('customers','Khách hàng');
?>
<h1 class="section-title">Khách hàng</h1><div class="table-card"><table class="table datatable"><thead><tr><th>Tên</th><th>Email</th><th>Đơn</th><th>Trạng thái</th><th></th></tr></thead><tbody><?php foreach($rows as $u): $formId='customer-status-'.(int)$u['id']; ?><tr><td><form id="<?= e($formId) ?>" method="post" data-confirm-submit="Xác nhận cập nhật khách hàng này?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"></form><?= e($u['name']) ?></td><td><?= e($u['email']) ?></td><td><?= (int)$u['order_count'] ?></td><td><select form="<?= e($formId) ?>" name="status" class="form-select form-select-sm"><option value="active" <?= $u['status']==='active'?'selected':'' ?>>Đang hoạt động</option><option value="locked" <?= $u['status']==='locked'?'selected':'' ?>>Đã khóa</option></select></td><td><button form="<?= e($formId) ?>" class="btn btn-sm btn-dark">Lưu</button></td></tr><?php endforeach; ?></tbody></table></div>
<?php admin_end(); ?>
