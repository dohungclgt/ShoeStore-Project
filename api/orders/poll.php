<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/order-ui.php';

$user = require_login();
ensure_payment_schema();

$stmt = db()->prepare("SELECT o.*,p.status payment_status,p.provider payment_provider FROM orders o LEFT JOIN payments p ON p.order_id=o.id WHERE o.user_id=? ORDER BY o.created_at DESC");
$stmt->execute([$user['id']]);
$orders = $stmt->fetchAll();

$itemsByOrder = [];
if ($orders) {
    $ids = array_column($orders, 'id');
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $itemStmt = db()->prepare("SELECT oi.*,p.image FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id IN ($ph) ORDER BY oi.id");
    $itemStmt->execute($ids);
    foreach ($itemStmt as $item) {
        $itemsByOrder[(int)$item['order_id']][] = [
            'id' => (int)$item['id'],
            'name' => $item['product_name'],
            'size' => $item['size'],
            'quantity' => (int)$item['quantity'],
            'price' => (float)$item['price'],
            'image' => $item['image'] ?: app_url('assets/img/review-placeholder.svg'),
        ];
    }
}

$payload = [];
foreach ($orders as $order) {
    $flags = order_action_flags($order);
    $payload[] = [
        'id' => (int)$order['id'],
        'code' => $order['code'],
        'status' => $order['status'],
        'status_label' => order_status_label($order['status']),
        'status_group' => order_status_group($order['status']),
        'badge_class' => order_status_badge_class($order['status']),
        'payment_method' => $order['payment_method'],
        'payment_status' => $order['payment_status'] ?? '',
        'payment_status_label' => payment_status_label($order['payment_status'] ?? ''),
        'payment_icon' => order_payment_icon($order['payment_method']),
        'total' => money($order['total']),
        'created_at' => date('d/m/Y H:i', strtotime($order['created_at'])),
        'actions' => $flags,
        'timeline' => order_timeline_steps($order['status'], $order['payment_status'] ?? null),
        'items' => $itemsByOrder[(int)$order['id']] ?? [],
    ];
}

json_response(['items' => $payload, 'ts' => time()]);
