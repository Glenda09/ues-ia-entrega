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

    'gcs' => [
        'project_id' => env('GCS_PROJECT_ID'),
        'bucket' => env('GCS_BUCKET'),
        // Ruta absoluta al archivo JSON de credenciales del servicio.
        // Alternativamente, puedes configurar GOOGLE_APPLICATION_CREDENTIALS.
        'key_file' => env('GCS_KEY_FILE'),
        'prefix' => env('GCS_PREFIX', null),
        'public_base_url' => env('GCS_PUBLIC_BASE_URL', 'https://storage.googleapis.com'),
    ],
];
