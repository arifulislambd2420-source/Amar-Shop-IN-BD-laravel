<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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
    'gtm' => [
        'enabled' => env('ENABLE_GTM', true),
    ],


    // bKash Tokenized Checkout — these .env values are only fallback
    // defaults. The admin-editable, encrypted values in site_settings (see
    // App\Filament\Pages\PaymentSettings, same pattern as Courier) always
    // take precedence when set; App\Services\Payment\BkashService reads
    // from site_settings first and falls back to these.
    // Customer SMS (BulkSMSBD-style HTTP API). Like bKash, the admin-editable
    // values in site_settings (Settings > SMS) win; these are fallbacks.
    // Off unless SMS_ENABLED=true or the admin toggle is on — so a dev .env
    // with real credentials can't text real customers by accident.
    'sms' => [
        'enabled' => env('SMS_ENABLED', false),
        'api_key' => env('SMS_API_KEY'),
        'sender_id' => env('SMS_SENDER_ID'),
        'url' => env('SMS_API_URL', 'https://bulksmsbd.net/api/smsapi'),
    ],

    'bkash' => [
        'app_key' => env('BKASH_APP_KEY'),
        'app_secret' => env('BKASH_APP_SECRET'),
        'username' => env('BKASH_USERNAME'),
        'password' => env('BKASH_PASSWORD'),
        'base_url' => env('BKASH_BASE_URL', 'https://tokenized.sandbox.bka.sh/v1.2.0-beta'),
    ],

];
