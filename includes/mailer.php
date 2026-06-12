<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

function mail_error_log_path(): string
{
    $dir = __DIR__ . '/../logs';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir . '/mail_error.log';
}

function log_mail_error(string $message): void
{
    $line = '[' . date('c') . '] ' . $message . PHP_EOL;
    error_log('Mail error: ' . $message);
    file_put_contents(mail_error_log_path(), $line, FILE_APPEND);
    $legacyDir = __DIR__ . '/../storage/logs';
    if (!is_dir($legacyDir)) {
        mkdir($legacyDir, 0775, true);
    }
    file_put_contents($legacyDir . '/mail.log', $line, FILE_APPEND);
}

function render_email_template(string $name, array $data = []): string
{
    $path = __DIR__ . '/../templates/email/' . $name . '.html';
    if (!is_file($path)) {
        return $data['body'] ?? '';
    }
    $html = file_get_contents($path);
    $defaults = [
        'base_url' => BASE_URL,
        'brand' => 'ShoeStore',
        'year' => date('Y'),
        'support_email' => 'support@shoestore.local',
        'orders_url' => absolute_url('user/orders.php'),
    ];
    foreach (array_replace($defaults, $data) as $key => $value) {
        $html = str_replace('{{' . $key . '}}', (string)$value, $html);
    }
    return $html;
}

function build_order_email_items(int $orderId): string
{
    $stmt = db()->prepare('SELECT product_name,size,price,quantity FROM order_items WHERE order_id=? ORDER BY id');
    $stmt->execute([$orderId]);
    $html = '';
    foreach ($stmt as $item) {
        $size = !empty($item['size']) ? ' · Size ' . e($item['size']) : '';
        $html .= '<tr><td style="padding:10px 0;border-bottom:1px solid #e5e7eb">' . e($item['product_name']) . $size . ' x ' . (int)$item['quantity'] . '</td><td style="padding:10px 0;border-bottom:1px solid #e5e7eb;text-align:right">' . money((float)$item['price'] * (int)$item['quantity']) . '</td></tr>';
    }
    return $html;
}

function order_email_data(array $order, string $headline, string $message): array
{
    $orderId = (int)($order['id'] ?? 0);
    return [
        'headline' => e($headline),
        'message' => e($message),
        'order_code' => e($order['code'] ?? ''),
        'order_status' => e(order_status_label($order['status'] ?? '')),
        'payment_method' => e($order['payment_method'] ?? ''),
        'shipping_address' => e($order['shipping_address'] ?? ''),
        'total' => money($order['total'] ?? 0),
        'items' => build_order_email_items($orderId),
        'orders_url' => absolute_url('user/orders.php?order_id=' . $orderId),
        'review_url' => absolute_url('user/review-order.php?order_id=' . $orderId),
        'products_url' => absolute_url('products.php'),
        'timeline' => e(order_status_label($order['status'] ?? '')),
    ];
}

function send_mail(string $to, string $subject, string $html, ?string $plainText = null): bool
{
    $cfg = require __DIR__ . '/../config/mail.php';
    if (($cfg['username'] ?? '') === '' || ($cfg['password'] ?? '') === '') {
        log_mail_error('SMTP username/password is not configured.');
        return false;
    }
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $cfg['host'];
        $mail->SMTPAuth = $cfg['username'] !== '';
        $mail->Username = $cfg['username'];
        $mail->Password = $cfg['password'];
        $mail->SMTPSecure = $cfg['encryption'];
        $mail->Port = $cfg['port'];
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->setFrom($cfg['from_email'], $cfg['from_name']);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = $plainText ?: trim(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />'], "\n", $html)));
        return $mail->send();
    } catch (Throwable $e) {
        log_mail_error($e->getMessage());
        return false;
    }
}
