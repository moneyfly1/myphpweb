<?php
// 通知配置 - 敏感信息从 .env 文件读取
return array(
    'telegram' => array(
        'enabled'   => env('TELEGRAM_ENABLED', 1),
        'bot_token' => env('TELEGRAM_BOT_TOKEN', ''),
        'chat_id'   => env('TELEGRAM_CHAT_ID', ''),
    ),
    'bark' => array(
        'enabled' => env('BARK_ENABLED', 1),
        'key'     => env('BARK_KEY', ''),
        'server'  => env('BARK_SERVER', 'https://api.day.app'),
    ),
    'email' => array(
        'enabled' => env('NOTIFY_EMAIL_ENABLED', 1),
        'to'      => env('NOTIFY_EMAIL_TO', ''),
    ),
);
