<?php

declare(strict_types=1);

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

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'neon' => [
        'base_url' => env('NEON_BASE_URL'),
        'api_key' => env('NEON_API_KEY'),
    ],

    'dropbox' => [
        'app_key' => env('DROPBOX_APP_KEY'),
        'app_secret' => env('DROPBOX_APP_SECRET'),
        'redirect_uri' => env('DROPBOX_REDIRECT_URI'),
        'upload_path' => env('DROPBOX_UPLOAD_PATH', '/uploads'),
        'require_basic_auth' => env('DROPBOX_OAUTH_REQUIRE_BASIC_AUTH', true),
        'basic_auth_user' => env('DROPBOX_OAUTH_BASIC_USER'),
        'basic_auth_password' => env('DROPBOX_OAUTH_BASIC_PASSWORD'),
    ],

];
