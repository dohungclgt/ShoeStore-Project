<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
ensure_support_schema();
$user = require_login();
$stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');
$stmt->execute([$user['id']]);
$count = (int)$stmt->fetchColumn();
$latest = db()->prepare('SELECT id,type,title,COALESCE(message,body) message,link,is_read,created_at FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 8');
$latest->execute([$user['id']]);
$items = $latest->fetchAll();
foreach ($items as &$item) {
    $item['icon'] = notification_icon($item['type'] ?? 'system');
    $item['link'] = notification_resolve_link($item, in_array($user['role_name'] ?? '', ['Super Admin','Admin','Staff'], true));
}
json_response(['count' => $count, 'items' => $items, 'ts' => time()]);
