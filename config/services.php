<?php

declare(strict_types=1);

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

    // Which SmsProvider implementation sends text messages (docs/09-engineering-principles.md).
    'sms' => [
        'primary' => env('SMS_PRIMARY_PROVIDER', 'log'),
    ],

    // PayPerlez SMS (docs/14, section 7). The key and the approved sender id come from the PayPerlez workspace.
    'payperlez' => [
        'api_key' => env('PAYPERLEZ_API_KEY'),
        'sender_id' => env('PAYPERLEZ_SMS_SENDER_ID'),
        'base_url' => env('PAYPERLEZ_BASE_URL', 'https://api.payperlez.org/api/v1'),
        'timeout_seconds' => 10,
    ],

    // Which WhatsAppGateway sends WhatsApp messages ('log' while Meta has not approved the account), and the Meta credentials (docs/14, section 3).
    'whatsapp' => [
        'driver' => env('WHATSAPP_DRIVER', 'log'),
    ],

    'meta' => [
        'verify_token' => env('META_VERIFY_TOKEN'),
        'app_secret' => env('META_APP_SECRET'),
        'access_token' => env('META_ACCESS_TOKEN'),
        'phone_number_id' => env('META_PHONE_NUMBER_ID'),
        'graph_url' => env('META_GRAPH_URL', 'https://graph.facebook.com'),
        'graph_version' => env('META_GRAPH_VERSION', 'v18.0'),
        'timeout_seconds' => 10,
    ],

];
