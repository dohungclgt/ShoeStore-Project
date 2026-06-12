<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/mailer.php';
$user = require_login();
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    if (($_POST['action'] ?? '') === 'change_password') {
        $fresh = current_user();
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (!password_verify($current, $fresh['password'])) {
            flash('error','Mật khẩu hiện tại không đúng.');
        } elseif (strlen($new) < 8) {
            flash('error','Mật khẩu mới phải có tối thiểu 8 ký tự.');
        } elseif ($new !== $confirm) {
            flash('error','Nhập lại mật khẩu mới không khớp.');
        } elseif (password_verify($new, $fresh['password'])) {
            flash('error','Mật khẩu mới không được trùng mật khẩu cũ.');
        } else {
            db()->prepare('UPDATE users SET password=? WHERE id=?')->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            send_mail($user['email'], 'Đổi mật khẩu thành công', '<p>Mật khẩu tài khoản ShoeStore của bạn vừa được thay đổi.</p>');
            audit_log('change_password','users',(int)$user['id']);
            unset($_SESSION['user_id']);
            flash('success','Đổi mật khẩu thành công. Vui lòng đăng nhập lại.');
            header('Location: ../auth/login.php');
            exit;
        }
        header('Location: profile.php'); exit;
    }
    $avatar = isset($_FILES['avatar']) ? upload_file($_FILES['avatar'],'uploads/avatars',['image/jpeg','image/png','image/webp']) : null;
    db()->prepare('UPDATE users SET name=?, phone=?, address=?, avatar=COALESCE(?,avatar) WHERE id=?')->execute([$_POST['name'],$_POST['phone'],$_POST['address'],$avatar,$user['id']]);
    flash('success','Đã cập nhật hồ sơ.');
    header('Location: profile.php'); exit;
}
render_header('Hồ sơ');
?>
<main class="container py-5"><h1 class="section-title">Hồ sơ người dùng</h1><div class="row g-4"><div class="col-md-4"><div class="table-card text-center"><img src="<?= e($user['avatar'] ?: 'https://ui-avatars.com/api/?name='.urlencode($user['name'])) ?>" class="rounded-circle mb-3" style="width:120px;height:120px;object-fit:cover" alt="Avatar"><h2 class="h5"><?= e($user['name']) ?></h2><p class="text-muted"><?= e($user['email']) ?></p></div></div><div class="col-md-8"><form method="post" enctype="multipart/form-data" class="table-card mb-4"><?= csrf_field() ?><label>Họ tên</label><input name="name" class="form-control mb-2" value="<?= e($user['name']) ?>"><label>Điện thoại</label><input name="phone" class="form-control mb-2" value="<?= e($user['phone']) ?>"><label>Địa chỉ</label><textarea name="address" class="form-control mb-2"><?= e($user['address']) ?></textarea><label>Avatar</label><input type="file" name="avatar" class="form-control mb-3"><button class="btn btn-dark">Lưu hồ sơ</button></form><form method="post" class="table-card" data-confirm-submit="Xác nhận đổi mật khẩu và đăng nhập lại?"><?= csrf_field() ?><input type="hidden" name="action" value="change_password"><h2 class="h5">Đổi mật khẩu</h2><label>Mật khẩu hiện tại</label><input name="current_password" type="password" class="form-control mb-2" required><label>Mật khẩu mới</label><input name="new_password" type="password" minlength="8" class="form-control mb-2" required><label>Nhập lại mật khẩu mới</label><input name="confirm_password" type="password" minlength="8" class="form-control mb-3" required><button class="btn btn-dark">Đổi mật khẩu</button></form></div></div></main>
<?php render_footer(); ?>
