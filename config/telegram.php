<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Telegram Bot Configuration
    |--------------------------------------------------------------------------
    |
    | bot_token       - BotFather-issued token used to call api.telegram.org.
    | admin_chat_id   - Chat id that receives admin notifications (registrations,
    |                   bot link events, error reports).
    | webhook_secret  - Shared secret sent in X-Telegram-Bot-Api-Secret-Token.
    |                   Must match what is configured when calling setWebhook.
    | webhook_url     - Full URL Telegram should POST to. Used by the
    |                   tgb:set-webhook artisan command. In production this
    |                   should be the public HTTPS URL. Leave empty locally
    |                   and use ngrok while developing.
    | bot_username    - Public username (no @) used to build t.me/<username>
    |                   deep links from the web settings page.
    | base_url        - Telegram Bot API base URL (rarely changed).
    |
    */

    'bot_token'      => env('TELEGRAM_BOT_TOKEN'),
    'admin_chat_id'  => env('TELEGRAM_ADMIN_CHAT_ID'),
    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
    'webhook_url'    => env('TELEGRAM_WEBHOOK_URL'),
    'bot_username'   => env('TELEGRAM_BOT_USERNAME', 'EnglishClassBot'),
    'base_url'       => env('TELEGRAM_BASE_URL', 'https://api.telegram.org/bot'),
];

