<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    rate_limit('login', 8, 300);
    $stmt = db()->prepare('SELECT * FROM users WHERE email=? AND status="active"');
    $stmt->execute([$_POST['email'] ?? '']);
    $user = $stmt->fetch();
    if ($user && password_verify($_POST['password'] ?? '', $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        audit_log('login', 'users', (int)$user['id']);
        $target = $_SESSION['intended_url'] ?? '../index.php';
        unset($_SESSION['intended_url']);
        header('Location: ' . $target);
        exit;
    }
    flash('error', 'Đăng nhập thất bại. Vui lòng kiểm tra email và mật khẩu.');
}
render_header('Đăng nhập');
?>
<main class="auth-page">
  <section class="auth-visual">
    <div>
      <span class="hero-kicker"><i class="fa-solid fa-shoe-prints"></i> ShoeStore Member</span>
      <h1>Đăng nhập</h1>
      <p>Theo dõi đơn hàng, lưu ưu đãi và mua sneaker nhanh hơn.</p>
    </div>
  </section>
  <section class="auth-panel">
    <form method="post" class="auth-card">
      <?= csrf_field() ?>
      <h2>Chào mừng trở lại</h2>
      <label>Email</label>
      <div class="input-icon"><i class="fa-solid fa-envelope"></i><input name="email" type="email" class="form-control" required placeholder="email@example.com"></div>
      <label>Mật khẩu</label>
      <div class="input-icon"><i class="fa-solid fa-lock"></i><input name="password" type="password" class="form-control" required placeholder="Mật khẩu"></div>
      <button class="btn btn-dark w-100 mt-3">Đăng nhập</button>
      <div class="auth-links"><a href="register.php">Tạo tài khoản</a><a href="forgot-password.php">Quên mật khẩu?</a></div>
      <p class="small text-muted mt-3 mb-0">Admin demo: admin@shoestore.local / password</p>
    </form>
  </section>
</main>
<?php render_footer(); ?>
