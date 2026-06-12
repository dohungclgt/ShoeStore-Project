<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/mailer.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    rate_limit('forgot', 4, 300);
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $stmt = db()->prepare('SELECT id,email FROM users WHERE email=?');
    $stmt->execute([$email ?: '']);
    $u = $stmt->fetch();
    if (!$u) {
        flash('error', 'Email này chưa được đăng ký trong hệ thống.');
    } else {
        $token = bin2hex(random_bytes(32));
        $resetLink = BASE_URL . '/auth/reset-password.php?token=' . urlencode($token);
        db()->prepare('INSERT INTO password_resets(user_id,token_hash,expires_at) VALUES(?,?,DATE_ADD(NOW(), INTERVAL 30 MINUTE))')->execute([$u['id'], hash('sha256', $token)]);
        $sent = send_mail($u['email'], 'Đặt lại mật khẩu ShoeStore', render_email_template('reset-password', [
            'reset_link' => $resetLink,
            'expires_minutes' => '30',
        ]), 'Link đặt lại mật khẩu ShoeStore: ' . $resetLink . ' (hết hạn sau 30 phút).');
        if ($sent) {
            flash('success', 'Đã gửi hướng dẫn đặt lại mật khẩu tới email của bạn.');
            header('Location: login.php');
            exit;
        }
        error_log('Mail error: forgot password email failed for user_id=' . $u['id']);
        flash('error', 'Không gửi được email đặt lại mật khẩu. Vui lòng kiểm tra cấu hình SMTP hoặc thử lại sau.');
    }
}
render_header('Quên mật khẩu');
?>
<main class="auth-page">
  <section class="auth-visual">
    <div><span class="hero-kicker"><i class="fa-solid fa-key"></i> Khôi phục tài khoản</span><h1>Quên mật khẩu</h1><p>Nhập email đã đăng ký để nhận liên kết đặt lại mật khẩu.</p></div>
  </section>
  <section class="auth-panel">
    <form method="post" class="auth-card">
      <?= csrf_field() ?>
      <h2>Nhận liên kết reset</h2>
      <label>Email</label>
      <div class="input-icon"><i class="fa-solid fa-envelope"></i><input name="email" type="email" class="form-control" required placeholder="email@example.com"></div>
      <button class="btn btn-dark w-100 mt-3">Gửi liên kết đặt lại</button>
      <div class="auth-links"><a href="login.php">Quay lại đăng nhập</a></div>
    </form>
  </section>
</main>
<?php render_footer(); ?>
