<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
ensure_support_schema();
$user = require_login();
$isAdmin = in_array($user['role_name'], ['Super Admin','Admin','Staff'], true);
$status = trim((string)($_GET['status'] ?? ''));
$q = trim((string)($_GET['q'] ?? ''));
$where = [];
$params = [];
if (!$isAdmin) { $where[] = 't.user_id=?'; $params[] = $user['id']; }
if ($status !== '') { $where[] = 't.status=?'; $params[] = $status; }
if ($q !== '') { $where[] = '(t.subject LIKE ? OR u.name LIKE ? OR u.email LIKE ?)'; array_push($params, "%$q%", "%$q%", "%$q%"); }
$sql = 'SELECT t.*,u.name,u.email,ass.name assigned_name FROM tickets t JOIN users u ON u.id=t.user_id LEFT JOIN users ass ON ass.id=t.assigned_to';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY t.updated_at DESC,t.created_at DESC LIMIT 100';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$items = [];
foreach ($stmt as $t) {
    $items[] = [
        'id' => (int)$t['id'],
        'subject' => $t['subject'],
        'topic' => $t['topic'],
        'status' => $t['status'],
        'status_label' => ticket_status_label($t['status']),
        'customer' => $t['name'],
        'email' => $t['email'],
        'assigned_name' => $t['assigned_name'],
        'unread' => $isAdmin ? (int)$t['admin_unread'] : (int)$t['user_unread'],
        'created_at' => $t['created_at'],
        'updated_at' => $t['updated_at'] ?: $t['created_at'],
    ];
}
json_response(['items' => $items, 'count' => count($items)]);
