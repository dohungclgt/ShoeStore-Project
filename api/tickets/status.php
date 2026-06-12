<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
ensure_support_schema();
$user = require_role(['Super Admin','Admin','Staff']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'Method not allowed'], 405);
verify_csrf();
$id = (int)($_POST['ticket_id'] ?? 0);
$status = (string)($_POST['status'] ?? '');
if (!in_array($status, ['open','pending','answered','closed'], true)) json_response(['error' => 'Trạng thái không hợp lệ.'], 422);
$assigned = ($_POST['assigned_to'] ?? '') !== '' ? (int)$_POST['assigned_to'] : null;
db()->prepare('UPDATE tickets SET status=?,assigned_to=?,updated_at=NOW() WHERE id=?')->execute([$status,$assigned,$id]);
$stmt = db()->prepare('SELECT subject,user_id FROM tickets WHERE id=?');
$stmt->execute([$id]);
$ticket = $stmt->fetch();
if ($ticket) create_notification((int)$ticket['user_id'], 'Cập nhật ticket', 'Ticket "' . $ticket['subject'] . '" đã chuyển sang ' . ticket_status_label($status) . '.', 'ticket', 'user/tickets.php?ticket_id=' . $id);
audit_log('update_ticket_status', 'tickets', $id, ['status' => $status, 'assigned_to' => $assigned]);
json_response(['ok' => true]);
