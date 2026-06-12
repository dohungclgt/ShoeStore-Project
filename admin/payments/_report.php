<?php
declare(strict_types=1);

function payment_report_filters(): array
{
    return [
        'from' => trim((string)($_GET['from'] ?? '')),
        'to' => trim((string)($_GET['to'] ?? '')),
        'provider' => trim((string)($_GET['provider'] ?? '')),
        'payment_status' => trim((string)($_GET['payment_status'] ?? '')),
        'order_status' => trim((string)($_GET['order_status'] ?? '')),
    ];
}

function payment_report_rows(array $filters): array
{
    $where = ["p.provider IN ('COD','VNPAY')"];
    $params = [];
    if ($filters['from'] !== '') { $where[] = 'DATE(o.created_at)>=?'; $params[] = $filters['from']; }
    if ($filters['to'] !== '') { $where[] = 'DATE(o.created_at)<=?'; $params[] = $filters['to']; }
    if (in_array($filters['provider'], ['COD','VNPAY'], true)) { $where[] = 'p.provider=?'; $params[] = $filters['provider']; }
    if ($filters['payment_status'] !== '') { $where[] = 'p.status=?'; $params[] = $filters['payment_status']; }
    if ($filters['order_status'] !== '') { $where[] = 'o.status=?'; $params[] = $filters['order_status']; }
    $sql = "SELECT o.id order_id,o.code,u.name customer,u.email,o.payment_method,o.status order_status,o.subtotal,o.discount,o.vat,o.shipping_fee,o.total,o.coupon_code,o.created_at,p.id payment_id,p.provider,p.status payment_status,p.transaction_id,p.updated_at paid_at,p.payment_attempts
            FROM orders o JOIN users u ON u.id=o.user_id LEFT JOIN payments p ON p.order_id=o.id
            WHERE " . implode(' AND ', $where) . " ORDER BY o.created_at DESC";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function payment_report_summary(array $rows): array
{
    $summary = ['revenue'=>0,'cod_revenue'=>0,'vnpay_revenue'=>0,'cod_orders'=>0,'vnpay_paid'=>0,'vnpay_failed'=>0,'pending'=>0,'discount'=>0,'vat'=>0,'net'=>0];
    foreach ($rows as $r) {
        $paidLike = in_array($r['payment_status'], ['paid','success','unpaid'], true);
        if ($paidLike) $summary['revenue'] += (float)$r['total'];
        if ($r['provider'] === 'COD') { $summary['cod_orders']++; if ($paidLike) $summary['cod_revenue'] += (float)$r['total']; }
        if ($r['provider'] === 'VNPAY') {
            if ($r['payment_status'] === 'paid') { $summary['vnpay_paid']++; $summary['vnpay_revenue'] += (float)$r['total']; }
            if ($r['payment_status'] === 'failed') $summary['vnpay_failed']++;
        }
        if ($r['payment_status'] === 'pending') $summary['pending']++;
        $summary['discount'] += (float)$r['discount'];
        $summary['vat'] += (float)$r['vat'];
    }
    $summary['net'] = $summary['revenue'] - $summary['vat'];
    return $summary;
}
