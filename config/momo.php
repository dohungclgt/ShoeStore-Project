<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';

return [
    'partner_code' => getenv('MOMO_PARTNER_CODE') ?: '',
    'access_key' => getenv('MOMO_ACCESS_KEY') ?: '',
    'secret_key' => getenv('MOMO_SECRET_KEY') ?: '',
    'endpoint' => 'https://test-payment.momo.vn/v2/gateway/api/create',
    'return_url' => BASE_URL . '/api/momo.php?action=return',
    'ipn_url' => BASE_URL . '/api/momo.php?action=ipn',
];
