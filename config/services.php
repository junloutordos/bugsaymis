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

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI'),
    ],

    'google_drive' => [
        'credentials'  => env('GOOGLE_DRIVE_CREDENTIALS'),
        'folder_id'    => env('GOOGLE_DRIVE_FOLDER_ID'),
        // DTS documents folder — falls back to the backup folder until a dedicated one is configured
        'dts_folder_id' => env('GOOGLE_DRIVE_DTS_FOLDER_ID', env('GOOGLE_DRIVE_FOLDER_ID')),
    ],

    'firebase' => [
        // Absolute path to the Firebase service-account JSON file.
        // Used for FCM push notifications to parent mobile devices.
        // Set FIREBASE_CREDENTIALS=/path/to/service-account.json in .env
        'credentials' => env('FIREBASE_CREDENTIALS'),
    ],

    'kms' => [
        'signing_key_arn' => env('KMS_SIGNING_KEY_ARN'),
        'region'          => env('AWS_DEFAULT_REGION', 'ap-southeast-1'),
    ],

    'sms_gate' => [
        // Local mode:  http://<phone-lan-ip>:8080/message
        // Cloud mode:  https://api.sms-gate.app/3rdparty/v1/messages
        'url'       => env('SMS_GATE_URL'),
        'username'  => env('SMS_GATE_USERNAME'),
        'password'  => env('SMS_GATE_PASSWORD'),
        'device_id' => env('SMS_GATE_DEVICE_ID'),
    ],

];
