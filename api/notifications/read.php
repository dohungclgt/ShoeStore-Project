<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
ensure_support_schema();
$user = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'Method not allowed'], 405);
verify_csrf();
$id = (int)($_POST['id'] ?? 0);
db()->prepare('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?')->execute([$id,$user['id']]);
json_response(['ok' => true]);
