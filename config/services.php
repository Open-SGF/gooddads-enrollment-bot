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
        'oauth' => [
            'clientId' => env('DROPBOX_APP_KEY', ''),
            'clientSecret' => env('DROPBOX_APP_SECRET', ''),
            'redirectUri' => env('DROPBOX_REDIRECT_URI', ''),
            'urlAuthorize' => 'https://www.dropbox.com/oauth2/authorize',
            'urlAccessToken' => 'https://api.dropboxapi.com/oauth2/token',
            'urlResourceOwnerDetails' => 'https://api.dropboxapi.com/2/users/get_current_account',
        ],
        'upload_path' => env('DROPBOX_UPLOAD_PATH', '/uploads'),
        'require_basic_auth' => env('DROPBOX_OAUTH_REQUIRE_BASIC_AUTH', true),
        'basic_auth_user' => env('DROPBOX_OAUTH_BASIC_USER'),
        'basic_auth_password' => env('DROPBOX_OAUTH_BASIC_PASSWORD'),
    ],

];
