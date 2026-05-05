<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Telegram Bot Configuration
    |--------------------------------------------------------------------------
    */

    'bot_token'      => env('TELEGRAM_BOT_TOKEN'),
    'admin_chat_id'  => env('TELEGRAM_ADMIN_CHAT_ID'),
    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET', 'englishclass_webhook_secret'),
    'base_url'       => 'https://api.telegram.org/bot',
];
