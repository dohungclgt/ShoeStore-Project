<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';

$config = [
    'tmn_code' => getenv('VNPAY_TMN_CODE') ?: 'UDOPNWS1',
    'hash_secret' => getenv('VNPAY_HASH_SECRET') ?: 'EBAHADUGCOEWYXCMYZRMTMLSHGKNRPBN',
    'payment_url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
    'return_url' => BASE_URL . '/api/vnpay.php?action=return',
    'ipn_url' => BASE_URL . '/api/vnpay.php?action=ipn',
];

$local = __DIR__ . '/vnpay.local.php';
if (is_file($local)) {
    $localConfig = require $local;
    if (is_array($localConfig)) $config = array_replace($config, $localConfig);
}

return $config;
