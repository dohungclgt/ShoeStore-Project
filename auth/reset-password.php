<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$tokenRow = null;
$tokenError = '';

if ($token === '' || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
    $tokenError = 'Token đặt lại mật khẩu không hợp lệ.';
} else {
    $hash = hash('sha256', $token);
    $stmt = db()->prepare('SELECT password_resets.*, (expires_at <= NOW()) AS is_expired FROM password_resets WHERE token_hash=? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$hash]);
    $tokenRow = $stmt->fetch() ?: null;
    if (!$tokenRow) {
        $tokenError = 'Token đặt lại mật khẩu không hợp lệ.';
    } elseif (!empty($tokenRow['used_at'])) {
        $tokenError = 'Token đặt lại mật khẩu đã được sử dụng.';
    } elseif ((int)$tokenRow['is_expired'] === 1) {
        $tokenError = 'Token đặt lại mật khẩu đã hết hạn.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $password = (string)($_POST['password'] ?? '');
    $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

    if ($tokenError !== '') {
        flash('error', $tokenError);
    } elseif ($password === '' || $passwordConfirm === '') {
        flash('error', 'Vui lòng nhập đầy đủ mật khẩu mới và nhập lại mật khẩu mới.');
    } elseif (strlen($password) < 8) {
        flash('error', 'Mật khẩu mới phải có tối thiểu 8 ký tự.');
    } elseif ($password !== $passwordConfirm) {
        flash('error', 'Mật khẩu nhập lại không khớp.');
    } else {
        db()->beginTransaction();
        try {
            $stmt = db()->prepare('SELECT * FROM password_resets WHERE id=? AND used_at IS NULL AND expires_at>NOW() FOR UPDATE');
            $stmt->execute([(int)$tokenRow['id']]);
            $freshToken = $stmt->fetch();
            if (!$freshToken) {
                throw new RuntimeException('Token đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.');
            }
            db()->prepare('UPDATE users SET password=? WHERE id=?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), (int)$freshToken['user_id']]);
            db()->prepare('UPDATE password_resets SET used_at=NOW() WHERE id=?')
                ->execute([(int)$freshToken['id']]);
            audit_log('reset_password', 'users', (int)$freshToken['user_id']);
            db()->commit();
            flash('success', 'Đặt lại mật khẩu thành công. Vui lòng đăng nhập bằng mật khẩu mới.');
            header('Location: login.php');
            exit;
        } catch (Throwable $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            flash('error', $e->getMessage());
        }
    }
}

render_header('Đặt lại mật khẩu');
?>
<main class="auth-page">
  <section class="auth-visual auth-visual-alt">
    <div><span class="hero-kicker"><i class="fa-solid fa-shield-halved"></i> Bảo mật</span><h1>Đặt lại mật khẩu</h1><p>Liên kết reset chỉ dùng được một lần và hết hạn sau 30 phút.</p></div>
  </section>
  <section class="auth-panel">
    <form method="post" class="auth-card">
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <h2>Mật khẩu mới</h2>
      <?php if ($tokenError !== ''): ?>
        <div class="alert alert-danger"><?= e($tokenError) ?></div>
      <?php endif; ?>
      <label>Mật khẩu mới</label>
      <div class="input-icon"><i class="fa-solid fa-lock"></i><input name="password" type="password" minlength="8" class="form-control" required placeholder="Tối thiểu 8 ký tự" <?= $tokenError !== '' ? 'disabled' : '' ?>></div>
      <label>Nhập lại mật khẩu mới</label>
      <div class="input-icon"><i class="fa-solid fa-lock"></i><input name="password_confirm" type="password" minlength="8" class="form-control" required placeholder="Nhập lại mật khẩu mới" <?= $tokenError !== '' ? 'disabled' : '' ?>></div>
      <button class="btn btn-dark w-100 mt-3" <?= $tokenError !== '' ? 'disabled' : '' ?>>Cập nhật mật khẩu</button>
      <div class="auth-links"><a href="forgot-password.php">Gửi lại liên kết đặt lại</a><a href="login.php">Đăng nhập</a></div>
    </form>
  </section>
</main>
<?php render_footer(); ?>
