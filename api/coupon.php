<?php
require_once __DIR__ . '/../includes/bootstrap.php';
ensure_commerce_schema();

$code = strtoupper(trim($_GET['code'] ?? $_POST['code'] ?? ''));
$subtotal = (float)($_GET['subtotal'] ?? $_POST['subtotal'] ?? 0);
$productInput = $_POST['product_ids'] ?? $_GET['product_ids'] ?? [];
if (!is_array($productInput)) $productInput = [$productInput];
$productIds = array_values(array_filter(array_map('intval', $productInput)));

if ($code === '') {
    remember_cart_coupon(null);
    json_response(['valid' => false, 'message' => 'Vui lòng nhập mã giảm giá.']);
}

$totals = calculate_coupon_totals($subtotal, $code, $productIds);
if (!$totals['coupon']) {
    remember_cart_coupon(null);
    json_response(['valid' => false, 'message' => $totals['coupon_message'] ?: 'Mã giảm giá không hợp lệ.']);
}

remember_cart_coupon($totals['coupon_code']);
json_response([
    'valid' => true,
    'code' => $totals['coupon_code'],
    'type' => $totals['coupon']['type'],
    'original' => $totals['subtotal'],
    'discount' => $totals['discount'],
    'shipping' => $totals['shipping'],
    'after_discount' => $totals['after_discount'],
    'vat' => $totals['vat'],
    'total' => $totals['total'],
    'final' => $totals['after_discount'],
    'free_shipping' => $totals['shipping'] <= 0,
    'original_label' => money($totals['subtotal']),
    'discount_label' => '-' . money($totals['discount']),
    'final_label' => money($totals['after_discount']),
    'vat_label' => money($totals['vat']),
    'total_label' => money($totals['total']),
    'message' => $totals['coupon_message'],
]);
