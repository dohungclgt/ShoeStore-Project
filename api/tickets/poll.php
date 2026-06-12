<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
ensure_support_schema();
$user = require_login();
$isAdmin = in_array($user['role_name'], ['Super Admin','Admin','Staff'], true);
if ($isAdmin) {
    $unread = (int)db()->query('SELECT COALESCE(SUM(admin_unread),0) FROM tickets WHERE status!="closed"')->fetchColumn();
    $open = (int)db()->query('SELECT COUNT(*) FROM tickets WHERE status!="closed"')->fetchColumn();
} else {
    $stmt = db()->prepare('SELECT COALESCE(SUM(user_unread),0) unread, COUNT(*) open_count FROM tickets WHERE user_id=? AND status!="closed"');
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch();
    $unread = (int)($row['unread'] ?? 0);
    $open = (int)($row['open_count'] ?? 0);
}
json_response(['unread' => $unread, 'open' => $open, 'ts' => time()]);
