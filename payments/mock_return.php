<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/mailer.php';

$user = require_login();
ensure_payment_schema();
ensure_commerce_schema();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('user/orders.php'));
    exit;
}
verify_csrf();

$orderId = (int)($_POST['order_id'] ?? 0);
$result = (string)($_POST['result'] ?? 'failed');
if (!in_array($result, ['success', 'failed', 'cancel'], true)) {
    $result = 'failed';
}

$stmt = db()->prepare("SELECT o.*, p.id payment_id, p.status payment_status, u.email FROM orders o JOIN payments p ON p.order_id=o.id JOIN users u ON u.id=o.user_id WHERE o.id=? AND o.user_id=? AND p.provider='MOCK' LIMIT 1");
$stmt->execute([$orderId, $user['id']]);
$row = $stmt->fetch();
if (!$row) {
    http_response_code(404);
    exit('Không tìm thấy giao dịch.');
}

$payload = [
    'gateway' => 'ShoeStore Pay',
    'order_id' => $orderId,
    'order_code' => $row['code'],
    'result' => $result,
    'amount' => (float)$row['total'],
    'processed_at' => date('c'),
];

db()->beginTransaction();
try {
    db()->prepare('INSERT INTO payment_logs(payment_id,provider,action,payload,valid_signature) VALUES(?,?,?,?,?)')->execute([
        $row['payment_id'],
        'MOCK',
        'mock_' . $result,
        json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        1,
    ]);

    if ($result === 'success') {
        $transactionId = 'MOCK' . date('YmdHis') . random_int(1000, 9999);
        db()->prepare("UPDATE payments SET status='paid',transaction_id=?,raw_response=?,amount=? WHERE id=?")->execute([
            $transactionId,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            (float)$row['total'],
            $row['payment_id'],
        ]);
        db()->prepare("UPDATE orders SET status='waiting_confirm' WHERE id=?")->execute([$orderId]);
        clear_paid_cart_items($orderId);
        create_notification((int)$row['user_id'], 'Thanh toán mô phỏng thành công', 'Đơn hàng '.$row['code'].' đã thanh toán thành công.', 'payment', notification_detail_link('order', $orderId, false));
        notify_admins('Thanh toán mô phỏng thành công', 'Đơn hàng '.$row['code'].' đã được thanh toán qua ShoeStore Pay.', 'payment', notification_detail_link('payment', (int)$row['payment_id'], true));
        audit_log('mock_payment_success', 'orders', $orderId, ['amount' => (float)$row['total'], 'transaction_id' => $transactionId]);
    } else {
        db()->prepare("UPDATE payments SET status='failed',raw_response=? WHERE id=?")->execute([
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $row['payment_id'],
        ]);
        db()->prepare("UPDATE orders SET status='pending_payment' WHERE id=?")->execute([$orderId]);
        create_notification((int)$row['user_id'], $result === 'cancel' ? 'Đã hủy giao dịch mô phỏng' : 'Thanh toán mô phỏng thất bại', 'Đơn hàng '.$row['code'].' vẫn ở trạng thái chờ thanh toán.', 'payment', notification_detail_link('order', $orderId, false));
        notify_admins('Giao dịch mô phỏng chưa thanh toán', 'Đơn hàng '.$row['code'].' chưa thanh toán thành công.', 'payment', notification_detail_link('payment', (int)$row['payment_id'], true));
        audit_log('mock_payment_failed', 'orders', $orderId, ['result' => $result]);
    }
    db()->commit();
} catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    flash('error', 'Không xử lý được giao dịch: ' . $e->getMessage());
    header('Location: ' . app_url('payments/mock_gateway.php?order_id=' . $orderId));
    exit;
}

if ($result === 'success') {
    send_mail($row['email'], 'Thanh toán mô phỏng thành công '.$row['code'], '<p>Đơn hàng '.$row['code'].' đã thanh toán thành công qua ShoeStore Pay.</p>');
    flash('success', 'Thanh toán mô phỏng thành công.');
} else {
    send_mail($row['email'], 'Thanh toán mô phỏng chưa thành công '.$row['code'], '<p>Đơn hàng '.$row['code'].' chưa thanh toán thành công. Bạn có thể thanh toán lại trong trang đơn hàng.</p>');
    flash('error', $result === 'cancel' ? 'Đã hủy giao dịch mô phỏng.' : 'Thanh toán mô phỏng thất bại.');
}

header('Location: ' . app_url('user/order-detail.php?order_id=' . $orderId));
exit;
