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

    // ESIGNON (전자위임 / e-proxy) — the external e-signature service the
    // esignon:sync command pulls completed proxy documents from.
    'esignon' => [
        'base_url' => env('ESIGNON_BASE_URL', 'https://docs.esignon.net/api'),
        'company_id' => env('ESIGNON_COMPANY_ID'),
        'email' => env('ESIGNON_MEMB_EMAIL'),
        'password' => env('ESIGNON_MEMB_PASSWORD'),
    ],

    // LibreOffice headless — converts 명부양식 XLSX to PDF. Optional: when the
    // path is unset the app looks for libreoffice/soffice on PATH, and mPDF
    // takes over when neither exists.
    'libreoffice' => [
        'path' => env('LIBREOFFICE_BIN'),
    ],

];
