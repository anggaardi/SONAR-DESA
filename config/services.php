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

    // Warga sign in with Google before the Cek Spek checklist (A4, F11). Values live in .env only.
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
        // ENGINEERING 2: every outbound call has a time limit and a failure path.
        'guzzle' => ['timeout' => 10, 'connect_timeout' => 5],
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // DataByte AI Gateway (OpenAI-compatible) for Vision & Document Processing
    'databyte' => [
        'key' => env('AI_API_KEY'),
        'base_url' => env('AI_BASE_URL', 'https://ai.databyte.co.id/gateway/v1'),
        'model' => env('AI_MODEL', 'google/gemini-1.5-flash'),
    ],

];
