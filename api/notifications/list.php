<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
ensure_support_schema();
$user = require_login();
$stmt = db()->prepare('SELECT id,type,title,COALESCE(message,body) message,link,is_read,created_at FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 80');
$stmt->execute([$user['id']]);
$items = $stmt->fetchAll();
foreach ($items as &$item) {
    $item['icon'] = notification_icon($item['type'] ?? 'system');
    $item['link'] = notification_resolve_link($item, in_array($user['role_name'] ?? '', ['Super Admin','Admin','Staff'], true));
}
$countStmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');
$countStmt->execute([$user['id']]);
json_response(['items' => $items, 'count' => (int)$countStmt->fetchColumn()]);
