<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';

$config = [
    'api_key' => getenv('OPENROUTER_API_KEY') ?: 'your openrouter api key',
    'endpoint' => 'https://openrouter.ai/api/v1/chat/completions',
    'model' => 'deepseek/deepseek-chat-v3',
    'fallback_model' => 'deepseek/deepseek-chat',
    'site_url' => BASE_URL,
    'app_name' => 'ShoeStore AI',
];

$local = __DIR__ . '/openrouter.local.php';
if (is_file($local)) {
    $localConfig = require $local;
    if (is_array($localConfig)) {
        $config = array_replace($config, $localConfig);
    }
}

return $config;
