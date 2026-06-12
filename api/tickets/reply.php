<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/mailer.php';
ensure_support_schema();
$user = require_login();
$isAdmin = in_array($user['role_name'], ['Super Admin','Admin','Staff'], true);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'Method not allowed'], 405);
verify_csrf();
$id = (int)($_POST['ticket_id'] ?? 0);
$message = trim((string)($_POST['message'] ?? ''));
if ($id <= 0 || $message === '') json_response(['error' => 'Vui lòng nhập nội dung phản hồi.'], 422);
$stmt = db()->prepare('SELECT t.*,u.email,u.name FROM tickets t JOIN users u ON u.id=t.user_id WHERE t.id=?');
$stmt->execute([$id]);
$ticket = $stmt->fetch();
if (!$ticket || (!$isAdmin && (int)$ticket['user_id'] !== (int)$user['id'])) json_response(['error' => 'Không tìm thấy ticket.'], 404);
if ($ticket['status'] === 'closed' && !$isAdmin) json_response(['error' => 'Ticket đã đóng. Vui lòng tạo ticket mới nếu cần hỗ trợ thêm.'], 422);
$file = isset($_FILES['attachment']) ? upload_ticket_attachment($_FILES['attachment']) : null;
db()->beginTransaction();
try {
    db()->prepare('INSERT INTO ticket_messages(ticket_id,user_id,message,attachment,created_at) VALUES(?,?,?,?,NOW())')->execute([$id,$user['id'],$message,$file['path'] ?? null]);
    $messageId = (int)db()->lastInsertId();
    if ($file) db()->prepare('INSERT INTO ticket_attachments(ticket_id,message_id,user_id,file_path,file_name,mime_type,file_size) VALUES(?,?,?,?,?,?,?)')->execute([$id,$messageId,$user['id'],$file['path'],$file['name'],$file['mime'],$file['size']]);
    if ($isAdmin) {
        $status = ($_POST['status'] ?? 'answered') === 'closed' ? 'closed' : 'answered';
        db()->prepare('UPDATE tickets SET status=?,user_unread=user_unread+1,updated_at=NOW() WHERE id=?')->execute([$status,$id]);
        create_notification((int)$ticket['user_id'], 'Ticket được phản hồi', 'Ticket "' . $ticket['subject'] . '" đã có phản hồi mới.', 'ticket', 'user/tickets.php?ticket_id=' . $id);
        send_mail($ticket['email'], 'Ticket được phản hồi', '<p>Ticket "' . e($ticket['subject']) . '" đã có phản hồi mới.</p>');
        audit_log('reply_ticket', 'tickets', $id);
    } else {
        db()->prepare("UPDATE tickets SET status='pending',admin_unread=admin_unread+1,updated_at=NOW() WHERE id=?")->execute([$id]);
        notify_admins('Ticket có phản hồi mới', 'Khách hàng vừa phản hồi ticket "' . $ticket['subject'] . '".', 'ticket', 'admin/support/index.php?ticket_id=' . $id);
        audit_log('user_reply_ticket', 'tickets', $id);
    }
    db()->commit();
    json_response(['ok' => true]);
} catch (Throwable $e) {
    db()->rollBack();
    json_response(['error' => 'Không gửi được phản hồi. Vui lòng thử lại sau.'], 500);
}
