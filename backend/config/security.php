<?php

return [
    'otp' => [
        'required' => (bool) env('OTP_REQUIRED', true),

        // log | sms_http
        'channel' => env('OTP_CHANNEL', 'log'),

        'ttl_minutes' => (int) env('OTP_TTL_MINUTES', 5),
        'cooldown_seconds' => (int) env('OTP_COOLDOWN_SECONDS', 45),
        'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),

        'debug_show' => (bool) env('OTP_DEBUG_SHOW', env('APP_ENV') === 'local'),

        'sms_http' => [
            'endpoint' => env('OTP_SMS_HTTP_ENDPOINT', ''),
            'token' => env('OTP_SMS_HTTP_TOKEN', ''),
            'timeout_seconds' => (int) env('OTP_SMS_HTTP_TIMEOUT', 10),
            'template' => env('OTP_SMS_HTTP_TEMPLATE', 'Your OTP code is {code}'),
        ],

        'telegram' => [
            'bot_token' => env('OTP_TELEGRAM_BOT_TOKEN', ''),
            'bot_username' => env('OTP_TELEGRAM_BOT_USERNAME', ''),
            'timeout_seconds' => (int) env('OTP_TELEGRAM_TIMEOUT', 10),
            'get_updates_limit' => (int) env('OTP_TELEGRAM_GET_UPDATES_LIMIT', 100),
            'template' => env('OTP_TELEGRAM_TEMPLATE', 'Your OTP code is {code}'),
        ],
    ],
];
