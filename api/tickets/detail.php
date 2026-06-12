<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
ensure_support_schema();
$user = require_login();
$isAdmin = in_array($user['role_name'], ['Super Admin','Admin','Staff'], true);
$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT t.*,u.name,u.email FROM tickets t JOIN users u ON u.id=t.user_id WHERE t.id=?');
$stmt->execute([$id]);
$ticket = $stmt->fetch();
if (!$ticket || (!$isAdmin && (int)$ticket['user_id'] !== (int)$user['id'])) json_response(['error' => 'Không tìm thấy ticket.'], 404);
if ($isAdmin) db()->prepare('UPDATE tickets SET admin_unread=0 WHERE id=?')->execute([$id]);
else db()->prepare('UPDATE tickets SET user_unread=0 WHERE id=?')->execute([$id]);
$m = db()->prepare('SELECT m.*,u.name,u.role_id,r.name role_name FROM ticket_messages m JOIN users u ON u.id=m.user_id JOIN roles r ON r.id=u.role_id WHERE m.ticket_id=? ORDER BY m.created_at ASC,m.id ASC');
$m->execute([$id]);
$messages = [];
foreach ($m as $row) {
    $messages[] = [
        'id' => (int)$row['id'],
        'sender' => $row['name'],
        'role' => $row['role_name'],
        'message' => $row['message'],
        'attachment' => $row['attachment'] ? app_url($row['attachment']) : null,
        'created_at' => $row['created_at'],
        'mine' => (int)$row['user_id'] === (int)$user['id'],
    ];
}
json_response(['ticket' => [
    'id' => (int)$ticket['id'],
    'subject' => $ticket['subject'],
    'topic' => $ticket['topic'],
    'status' => $ticket['status'],
    'status_label' => ticket_status_label($ticket['status']),
    'customer' => $ticket['name'],
    'email' => $ticket['email'],
], 'messages' => $messages]);
