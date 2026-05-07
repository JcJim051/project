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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
        'timeout' => (int) env('GOOGLE_HTTP_TIMEOUT', 120),
        'connect_timeout' => (int) env('GOOGLE_HTTP_CONNECT_TIMEOUT', 20),
        'download_retries' => (int) env('GOOGLE_DOWNLOAD_RETRIES', 4),
    ],

    'attachments_pdf' => [
        'python_bin' => env('ATTACHMENTS_PDF_PYTHON_BIN', 'python3'),
        'script_path' => env('ATTACHMENTS_PDF_SCRIPT_PATH', base_path('scripts/generate_attachment_pdfs.py')),
    ],

];
