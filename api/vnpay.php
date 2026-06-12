<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/mailer.php';

$cfg = require __DIR__ . '/../config/vnpay.php';
ensure_payment_schema();
ensure_commerce_schema();

function vnp_hash(array $data, string $secret): string
{
    unset($data['vnp_SecureHash'], $data['vnp_SecureHashType'], $data['action']);
    ksort($data);
    $pairs = [];
    foreach ($data as $key => $value) {
        if ($value === '' || $value === null) continue;
        $pairs[] = $key . '=' . urlencode((string)$value);
    }
    return hash_hmac('sha512', implode('&', $pairs), $secret);
}

function vnp_payment_url(array $order, array $cfg): string
{
    $amount = (int)round(((float)$order['total']) * 100);
    $data = [
        'vnp_Version' => '2.1.0',
        'vnp_Command' => 'pay',
        'vnp_TmnCode' => $cfg['tmn_code'],
        'vnp_Amount' => $amount,
        'vnp_CurrCode' => 'VND',
        'vnp_TxnRef' => $order['code'],
        'vnp_OrderInfo' => 'Thanh toan don hang ' . $order['code'],
        'vnp_OrderType' => 'billpayment',
        'vnp_Locale' => 'vn',
        'vnp_ReturnUrl' => $cfg['return_url'],
        'vnp_IpAddr' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        'vnp_CreateDate' => date('YmdHis'),
    ];
    $data['vnp_SecureHash'] = vnp_hash($data, $cfg['hash_secret']);
    return $cfg['payment_url'] . '?' . http_build_query($data);
}

$action = $_GET['action'] ?? 'create';

if ($action === 'create') {
    $user = require_login();
    $orderId = (int)($_GET['order_id'] ?? 0);
    $stmt = db()->prepare("SELECT o.*,p.id payment_id,p.status payment_status FROM orders o JOIN payments p ON p.order_id=o.id WHERE o.id=? AND o.user_id=? AND p.provider='VNPAY' LIMIT 1");
    $stmt->execute([$orderId, $user['id']]);
    $order = $stmt->fetch();
    if (!$order || $order['payment_method'] !== 'VNPAY') exit('Phương thức thanh toán không hợp lệ.');
    if (!in_array($order['status'], ['pending_payment','cho_thanh_toan'], true) || $order['payment_status'] === 'paid') exit('Đơn hàng này không còn ở trạng thái chờ thanh toán.');
    db()->prepare("UPDATE payments SET status='pending',payment_attempts=payment_attempts+1,amount=? WHERE id=?")->execute([(float)$order['total'], $order['payment_id']]);
    db()->prepare('INSERT INTO payment_logs(payment_id,provider,action,payload,valid_signature) VALUES(?,?,?,?,?)')->execute([
        $order['payment_id'], 'VNPAY', 'create_payment_url',
        json_encode(['order_id'=>$orderId,'code'=>$order['code'],'amount'=>(int)round(((float)$order['total'])*100)], JSON_UNESCAPED_UNICODE),
        1
    ]);
    header('Location: ' . vnp_payment_url($order, $cfg));
    exit;
}

$payload = $_GET;
$valid = isset($payload['vnp_SecureHash']) && hash_equals((string)$payload['vnp_SecureHash'], vnp_hash($payload, $cfg['hash_secret']));
$code = (string)($payload['vnp_TxnRef'] ?? '');
$responseOk = ($payload['vnp_ResponseCode'] ?? '') === '00';
$transactionOk = ($payload['vnp_TransactionStatus'] ?? '') === '00';
$stmt = db()->prepare("SELECT p.id payment_id,o.id order_id,o.*,u.email FROM orders o JOIN payments p ON p.order_id=o.id JOIN users u ON u.id=o.user_id WHERE o.code=? AND p.provider='VNPAY' LIMIT 1");
$stmt->execute([$code]);
$row = $stmt->fetch();
$amountOk = false;
$success = false;

if ($row) {
    $expectedAmount = (int)round(((float)$row['total']) * 100);
    $amountOk = ((int)($payload['vnp_Amount'] ?? 0) === $expectedAmount);
    $success = $valid && $responseOk && $transactionOk && $amountOk && $code === $row['code'];
    db()->prepare('INSERT INTO payment_logs(payment_id,provider,action,payload,valid_signature) VALUES(?,?,?,?,?)')->execute([$row['payment_id'],'VNPAY',$action,json_encode($payload, JSON_UNESCAPED_UNICODE),($valid && $amountOk) ? 1 : 0]);
    if ($success) {
        db()->prepare("UPDATE payments SET status='paid',transaction_id=?,raw_response=?,amount=? WHERE id=?")->execute([$payload['vnp_TransactionNo'] ?? null,json_encode($payload, JSON_UNESCAPED_UNICODE),(float)$row['total'],$row['payment_id']]);
        db()->prepare("UPDATE orders SET status='waiting_confirm' WHERE id=?")->execute([$row['order_id']]);
        clear_paid_cart_items((int)$row['order_id']);
        create_notification((int)$row['user_id'], 'Thanh toán VNPay thành công', 'Đơn hàng '.$row['code'].' đã thanh toán thành công.', 'payment', notification_detail_link('payment', (int)$row['payment_id'], false));
        notify_admins('Thanh toán VNPay thành công', 'Đơn hàng '.$row['code'].' đã được thanh toán.', 'payment', notification_detail_link('payment', (int)$row['payment_id'], true));
        send_mail($row['email'], 'Thanh toán VNPay thành công '.$row['code'], '<p>Đơn hàng '.$row['code'].' đã thanh toán thành công.</p>');
        audit_log('payment_success','orders',(int)$row['order_id'],['provider'=>'VNPAY','amount'=>$expectedAmount]);
    } else {
        db()->prepare("UPDATE payments SET status='failed',raw_response=? WHERE id=?")->execute([json_encode($payload, JSON_UNESCAPED_UNICODE),$row['payment_id']]);
        db()->prepare("UPDATE orders SET status='pending_payment' WHERE id=?")->execute([$row['order_id']]);
        create_notification((int)$row['user_id'], 'Thanh toán VNPay thất bại', 'Đơn hàng '.$row['code'].' thanh toán thất bại. Sản phẩm vẫn được giữ trong giỏ hàng.', 'payment', notification_detail_link('payment', (int)$row['payment_id'], false));
        audit_log('payment_failed','orders',(int)$row['order_id'],['provider'=>'VNPAY','valid'=>$valid,'amount_ok'=>$amountOk,'response'=>$payload['vnp_ResponseCode'] ?? null,'transaction'=>$payload['vnp_TransactionStatus'] ?? null]);
    }
}

if ($action === 'ipn') {
    json_response(['RspCode' => $success ? '00' : '97', 'Message' => $success ? 'Confirm Success' : 'Invalid payment']);
}

flash($success ? 'success' : 'error', $success ? 'Thanh toán VNPay thành công.' : 'Thanh toán VNPay thất bại hoặc thông tin thanh toán không hợp lệ.');
header('Location: ' . app_url('user/orders.php'));
exit;
