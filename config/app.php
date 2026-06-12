<?php
declare(strict_types=1);

define('APP_ENV', 'local');

define('USE_NGROK', false);

define('LOCAL_HOST', 'http://localhost');
define('LOCAL_SUBDIR', '/shoestore');

define('NGROK_HOST', 'https://965c-2402-800-5d0e-a650-6d3b-6272-d4e7-f6c2.ngrok-free.app');
define('NGROK_SUBDIR', '/shoestore');

define('BASE_URL', rtrim(USE_NGROK ? NGROK_HOST . NGROK_SUBDIR : LOCAL_HOST . LOCAL_SUBDIR, '/'));

define('VAT_RATE', 0.05);

if (!function_exists('app_url')) {
    function app_url(string $path = ''): string
    {
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        $base = rtrim(BASE_URL, '/');
        $path = ltrim($path, '/');
        return $path !== '' ? $base . '/' . $path : $base . '/';
    }
}
