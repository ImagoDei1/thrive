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

    /*
    | Bunny Stream (VOD). The account key provisions one video library per
    | provider; each library's own API key and CDN host live on
    | provider_profiles. See docs/bunny-stream.md.
    */
    'bunny' => [
        'api_key'          => env('BUNNY_API_KEY'),
        'account_api_base' => env('BUNNY_ACCOUNT_API_BASE', 'https://api.bunny.net'),
        'stream_api_base'  => env('BUNNY_STREAM_API_BASE', 'https://video.bunnycdn.com/library'),
        'tus_endpoint'     => env('BUNNY_TUS_ENDPOINT', 'https://video.bunnycdn.com/tusupload'),
        'embed_base'       => env('BUNNY_EMBED_BASE', 'https://iframe.mediadelivery.net/embed'),
        'webhook_secret'   => env('BUNNY_WEBHOOK_SECRET'),
        'token_auth'       => (bool) env('BUNNY_TOKEN_AUTH', true),
        'url_ttl_seconds'  => (int) env('BUNNY_URL_TTL_SECONDS', 21600),
        'upload_ttl_seconds' => (int) env('BUNNY_UPLOAD_TTL_SECONDS', 86400),
        'max_upload_bytes' => (int) env('BUNNY_MAX_UPLOAD_BYTES', 10 * 1024 * 1024 * 1024),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
