<?php
declare(strict_types=1);

function order_cancelable_statuses(): array
{
    return ['pending_payment', 'waiting_pickup', 'waiting_confirm', 'cho_thanh_toan', 'cho_xac_nhan'];
}

function order_returnable_statuses(): array
{
    return ['delivered', 'completed', 'da_giao', 'hoan_thanh'];
}

function order_status_badge_class(string $status): string
{
    return [
        'pending_payment' => 'order-badge-waitpay',
        'cho_thanh_toan' => 'order-badge-waitpay',
        'waiting_confirm' => 'order-badge-confirm',
        'cho_xac_nhan' => 'order-badge-confirm',
        'waiting_pickup' => 'order-badge-pickup',
        'packing' => 'order-badge-packing',
        'dang_dong_goi' => 'order-badge-packing',
        'shipping' => 'order-badge-shipping',
        'dang_van_chuyen' => 'order-badge-shipping',
        'delivered' => 'order-badge-delivered',
        'da_giao' => 'order-badge-delivered',
        'completed' => 'order-badge-completed',
        'hoan_thanh' => 'order-badge-completed',
        'cancelled' => 'order-badge-cancelled',
        'da_huy' => 'order-badge-cancelled',
        'returned' => 'order-badge-returned',
        'hoan_tra' => 'order-badge-returned',
    ][$status] ?? 'order-badge-returned';
}

function order_status_group(string $status): string
{
    return [
        'pending_payment' => 'pending_payment',
        'cho_thanh_toan' => 'pending_payment',
        'waiting_confirm' => 'waiting_confirm',
        'cho_xac_nhan' => 'waiting_confirm',
        'waiting_pickup' => 'waiting_pickup',
        'packing' => 'packing',
        'dang_dong_goi' => 'packing',
        'shipping' => 'shipping',
        'dang_van_chuyen' => 'shipping',
        'delivered' => 'delivered',
        'da_giao' => 'delivered',
        'completed' => 'delivered',
        'hoan_thanh' => 'delivered',
        'cancelled' => 'cancelled',
        'da_huy' => 'cancelled',
        'returned' => 'returned',
        'hoan_tra' => 'returned',
    ][$status] ?? $status;
}

function order_payment_icon(string $method): string
{
    return match (strtoupper($method)) {
        'VNPAY' => 'fa-building-columns',
        'MOMO' => 'fa-wallet',
        default => 'fa-money-bill-wave',
    };
}

function order_timeline_steps(string $status, ?string $paymentStatus = null): array
{
    $status = order_status_group($status);
    $rank = [
        'pending_payment' => 1,
        'waiting_confirm' => 2,
        'waiting_pickup' => 3,
        'packing' => 4,
        'shipping' => 5,
        'delivered' => 6,
        'returned' => 6,
        'cancelled' => 1,
    ][$status] ?? 1;
    $paid = in_array($paymentStatus, ['paid', 'success'], true) || $rank >= 2;
    $steps = [
        ['key' => 'created', 'label' => 'Đặt hàng', 'done' => true],
        ['key' => 'paid', 'label' => 'Thanh toán', 'done' => $paid],
        ['key' => 'confirmed', 'label' => 'Xác nhận', 'done' => $rank >= 2],
        ['key' => 'packing', 'label' => 'Đóng gói', 'done' => $rank >= 4],
        ['key' => 'shipping', 'label' => 'Giao hàng', 'done' => $rank >= 5],
        ['key' => 'completed', 'label' => 'Hoàn tất', 'done' => $rank >= 6],
    ];
    if ($status === 'cancelled') {
        $steps[] = ['key' => 'cancelled', 'label' => 'Đã hủy', 'done' => true, 'danger' => true];
    }
    return $steps;
}

function order_action_flags(array $order): array
{
    $status = (string)($order['status'] ?? '');
    $paymentStatus = (string)($order['payment_status'] ?? '');
    return [
        'pay' => strtoupper((string)($order['payment_method'] ?? '')) === 'VNPAY'
            && $paymentStatus === 'pending'
            && in_array($status, ['pending_payment', 'cho_thanh_toan'], true),
        'cancel' => in_array($status, order_cancelable_statuses(), true),
        'return' => in_array($status, order_returnable_statuses(), true),
        'review' => in_array($status, order_returnable_statuses(), true),
    ];
}
