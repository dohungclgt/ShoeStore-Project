<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/mailer.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    rate_limit('register', 5, 300);
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    if (!$email || strlen($_POST['password'] ?? '') < 8) {
        flash('error', 'Email hoặc mật khẩu không hợp lệ. Mật khẩu cần tối thiểu 8 ký tự.');
    } else {
        $role = db()->query("SELECT id FROM roles WHERE name='Customer'")->fetchColumn();
        $stmt = db()->prepare('INSERT INTO users(role_id,name,email,password,phone,address) VALUES(?,?,?,?,?,?)');
        try {
            $stmt->execute([$role, trim($_POST['name']), $email, password_hash($_POST['password'], PASSWORD_DEFAULT), trim($_POST['phone'] ?? ''), trim($_POST['address'] ?? '')]);
            $uid = (int)db()->lastInsertId();
            $token = bin2hex(random_bytes(32));
            db()->prepare('INSERT INTO email_verifications(user_id,token_hash,expires_at) VALUES(?,?,DATE_ADD(NOW(), INTERVAL 24 HOUR))')->execute([$uid, hash('sha256', $token)]);
            send_mail($email, 'Xác thực email ShoeStore', '<p>Vui lòng xác thực tài khoản ShoeStore:</p><p><a href="'.e(absolute_url('auth/verify-email.php?token=' . urlencode($token))).'">Xác thực tài khoản</a></p>');
            unset($_SESSION['user_id']);
            flash('success', 'Đăng ký thành công. Vui lòng đăng nhập để tiếp tục.');
            header('Location: login.php');
            exit;
        } catch (PDOException $e) {
            flash('error', 'Email đã tồn tại trong hệ thống.');
        }
    }
}
render_header('Đăng ký');
?>
<main class="auth-page">
  <section class="auth-visual auth-visual-alt">
    <div>
      <span class="hero-kicker"><i class="fa-solid fa-star"></i> Tài khoản mới</span>
      <h1>Đăng ký</h1>
      <p>Nhận thông báo đơn hàng, coupon và lịch sử hỗ trợ trong một tài khoản.</p>
    </div>
  </section>
  <section class="auth-panel">
    <form method="post" class="auth-card">
      <?= csrf_field() ?>
      <h2>Tạo tài khoản</h2>
      <label>Họ tên</label><div class="input-icon"><i class="fa-solid fa-user"></i><input name="name" class="form-control" required placeholder="Nguyễn Văn A"></div>
      <label>Email</label><div class="input-icon"><i class="fa-solid fa-envelope"></i><input name="email" type="email" class="form-control" required placeholder="email@example.com"></div>
      <label>Mật khẩu</label><div class="input-icon"><i class="fa-solid fa-lock"></i><input name="password" type="password" minlength="8" class="form-control" required placeholder="Tối thiểu 8 ký tự"></div>
      <label>Điện thoại</label><div class="input-icon"><i class="fa-solid fa-phone"></i><input name="phone" class="form-control" placeholder="Số điện thoại"></div>
      <label>Địa chỉ</label><div class="input-icon"><i class="fa-solid fa-location-dot"></i><textarea name="address" class="form-control" placeholder="Địa chỉ nhận hàng"></textarea></div>
      <button class="btn btn-dark w-100 mt-3">Tạo tài khoản</button>
      <div class="auth-links"><a href="login.php">Đã có tài khoản? Đăng nhập</a></div>
    </form>
  </section>
</main>
<?php render_footer(); ?>
