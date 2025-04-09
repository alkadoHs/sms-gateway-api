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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'smsgateway' => [
        'base_url' => env('SMS_GATEWAY_BASE_URL'),
        'username' => env('SMS_GATEWAY_USER'),
        'password' => env('SMS_GATEWAY_PASS'),
        'default_sim' => env('SMS_GATEWAY_SIM_SLOT'),
        'delivery_report' => env('SMS_GATEWAY_DELIVERY_REPORT'),
        'webhook_secret' => env('SMS_GATEWAY_WEBHOOK_SECRET'),
        'webhook_tolerance' => env('SMS_GATEWAY_WEBHOOK_TOLERANCE', 300), // Default to 5 minutes
    ],

];
