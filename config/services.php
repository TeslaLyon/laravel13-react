<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | V免签网关配置
    |--------------------------------------------------------------------------
    */
    'vmq' => [
        'base_url' => env('VMQ_GATEWAY_URL', 'http://127.0.0.1:8080'),
        'cashier_url' => env('VMQ_CASHIER_URL', 'https://pay.536969.xyz'),
        'app_key' => env('VMQ_APP_KEY'),
        'app_secret' => env('VMQ_APP_SECRET'),
        'timeout' => (int) env('VMQ_API_TIMEOUT', 15),
        'connect_timeout' => (int) env('VMQ_API_CONNECT_TIMEOUT', 5),
        'max_retries' => (int) env('VMQ_API_MAX_RETRIES', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cloudflare Turnstile 验证配置
    |--------------------------------------------------------------------------
    */
    'turnstile' => [
        'site_key'   => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
    ],

];
