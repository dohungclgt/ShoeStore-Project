<?php
require_once __DIR__ . '/../includes/bootstrap.php';
ensure_support_schema();
$user = current_user();
if (!$user) {
    json_response(['count' => 0, 'items' => []]);
}

if (($_POST['action'] ?? $_GET['action'] ?? '') === 'mark_read') {
    verify_csrf();
    db()->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([$user['id']]);
}

$stmt = db()->prepare('SELECT id,type,title,COALESCE(message,body) message,link,is_read,created_at FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 8');
$stmt->execute([$user['id']]);
$items = $stmt->fetchAll();
$count = array_reduce($items, fn($carry, $n) => $carry + ((int)$n['is_read'] === 0 ? 1 : 0), 0);
foreach ($items as &$item) {
    $item['icon'] = notification_icon($item['type'] ?? 'system');
    $item['link'] = notification_resolve_link($item, in_array($user['role_name'] ?? '', ['Super Admin','Admin','Staff'], true));
}
json_response(['count' => $count, 'items' => $items]);
