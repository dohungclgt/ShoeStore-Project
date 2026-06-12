<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
ensure_support_schema();
$user = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'Method not allowed'], 405);
verify_csrf();
$subject = trim((string)($_POST['subject'] ?? ''));
$topic = trim((string)($_POST['topic'] ?? 'general'));
$message = trim((string)($_POST['message'] ?? ''));
if ($subject === '' || $message === '') json_response(['error' => 'Vui lòng nhập chủ đề và nội dung.'], 422);
$file = isset($_FILES['attachment']) ? upload_ticket_attachment($_FILES['attachment']) : null;
db()->beginTransaction();
try {
    db()->prepare("INSERT INTO tickets(user_id,subject,topic,status,attachment,admin_unread,created_at) VALUES(?,?,?,?,?,1,NOW())")->execute([$user['id'],$subject,$topic,'open',$file['path'] ?? null]);
    $ticketId = (int)db()->lastInsertId();
    db()->prepare('INSERT INTO ticket_messages(ticket_id,user_id,message,attachment,created_at) VALUES(?,?,?,?,NOW())')->execute([$ticketId,$user['id'],$message,$file['path'] ?? null]);
    $messageId = (int)db()->lastInsertId();
    if ($file) db()->prepare('INSERT INTO ticket_attachments(ticket_id,message_id,user_id,file_path,file_name,mime_type,file_size) VALUES(?,?,?,?,?,?,?)')->execute([$ticketId,$messageId,$user['id'],$file['path'],$file['name'],$file['mime'],$file['size']]);
    notify_admins('Có ticket mới', 'Ticket "' . $subject . '" vừa được tạo.', 'ticket', 'admin/support/index.php?ticket_id=' . $ticketId);
    audit_log('create_ticket', 'tickets', $ticketId);
    db()->commit();
    json_response(['ok' => true, 'ticket_id' => $ticketId]);
} catch (Throwable $e) {
    db()->rollBack();
    json_response(['error' => 'Không tạo được ticket. Vui lòng thử lại sau.'], 500);
}
