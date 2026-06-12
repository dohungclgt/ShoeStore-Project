<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$hash = hash('sha256', $_GET['token'] ?? '');
$stmt = db()->prepare('SELECT * FROM email_verifications WHERE token_hash=? AND verified_at IS NULL AND expires_at>NOW() ORDER BY id DESC LIMIT 1');
$stmt->execute([$hash]);
$row = $stmt->fetch();
if ($row) {
    db()->prepare('UPDATE users SET email_verified_at=NOW() WHERE id=?')->execute([$row['user_id']]);
    db()->prepare('UPDATE email_verifications SET verified_at=NOW() WHERE id=?')->execute([$row['id']]);
    flash('success','Email đã được xác thực.');
} else {
    flash('error','Liên kết xác thực không hợp lệ.');
}
header('Location: login.php');
