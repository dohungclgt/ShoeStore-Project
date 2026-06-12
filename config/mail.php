<?php
declare(strict_types=1);

$config = [
    'host' => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
    'username' => getenv('SMTP_USERNAME') ?: 'your email username',
    'password' => getenv('SMTP_PASSWORD') ?: 'your app password',
    'port' => (int)(getenv('SMTP_PORT') ?: 587),
    'encryption' => getenv('SMTP_SECURE') ?: 'tls',
    'from_email' => getenv('SMTP_FROM_EMAIL') ?: (getenv('SMTP_USERNAME') ?: 'your email username'),
    'from_name' => getenv('SMTP_FROM_NAME') ?: 'ShoeStore',
];

$local = __DIR__ . '/mail.local.php';
if (is_file($local)) {
    $config = array_replace($config, require $local);
}

return $config;
