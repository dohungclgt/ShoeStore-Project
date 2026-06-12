<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/order-ui.php';

$user = require_login();
ensure_payment_schema();

$orderId = (int)($_GET['order_id'] ?? $_GET['id'] ?? 0);
$stmt = db()->prepare("SELECT o.*,p.status payment_status,p.provider payment_provider,p.transaction_id FROM orders o LEFT JOIN payments p ON p.order_id=o.id WHERE o.id=? AND o.user_id=? LIMIT 1");
$stmt->execute([$orderId, $user['id']]);
$order = $stmt->fetch();
if (!$order) {
    json_response(['error' => 'Không tìm thấy đơn hàng.'], 404);
}

$itemsStmt = db()->prepare('SELECT oi.*,p.image,p.slug FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id=? ORDER BY oi.id');
$itemsStmt->execute([$orderId]);
$items = [];
foreach ($itemsStmt as $item) {
    $items[] = [
        'id' => (int)$item['id'],
        'product_id' => (int)$item['product_id'],
        'name' => $item['product_name'],
        'size' => $item['size'],
        'quantity' => (int)$item['quantity'],
        'price' => money($item['price']),
        'line_total' => money((float)$item['price'] * (int)$item['quantity']),
        'image' => $item['image'] ?: app_url('assets/img/review-placeholder.svg'),
        'url' => $item['slug'] ? app_url('product.php?slug=' . rawurlencode($item['slug'])) : '#',
    ];
}

json_response([
    'order' => [
        'id' => (int)$order['id'],
        'code' => $order['code'],
        'created_at' => date('d/m/Y H:i', strtotime($order['created_at'])),
        'status' => $order['status'],
        'status_label' => order_status_label($order['status']),
        'badge_class' => order_status_badge_class($order['status']),
        'payment_method' => $order['payment_method'],
        'payment_status' => $order['payment_status'] ?? '',
        'payment_status_label' => payment_status_label($order['payment_status'] ?? ''),
        'transaction_id' => $order['transaction_id'] ?? '',
        'shipping_name' => $order['shipping_name'],
        'shipping_phone' => $order['shipping_phone'],
        'shipping_address' => $order['shipping_address'],
        'coupon_code' => $order['coupon_code'],
        'subtotal' => money($order['subtotal']),
        'discount' => money($order['discount']),
        'shipping_fee' => money($order['shipping_fee']),
        'vat' => money($order['vat']),
        'total' => money($order['total']),
        'pdf_url' => app_url('invoice/generate-pdf.php?order_id=' . (int)$order['id']),
        'excel_url' => app_url('invoice/generate-excel.php?order_id=' . (int)$order['id']),
        'timeline' => order_timeline_steps($order['status'], $order['payment_status'] ?? null),
        'actions' => order_action_flags($order),
    ],
    'items' => $items,
]);
