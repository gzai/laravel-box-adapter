<?php

return [
    'client_id' => env('BOX_CLIENT_ID'),
    'client_secret' => env('BOX_CLIENT_SECRET'),
    'redirect' => env('BOX_REDIRECT_URI'),
    'authorize_url' => 'https://account.box.com/api/oauth2/authorize',
    'token_url' => 'https://api.box.com/oauth2/token',
    'api_url' => 'https://api.box.com/2.0',
    'upload_url' => 'https://upload.box.com/api/2.0',
    'routes_enabled' => true,
    'user_enabled' => false,
    'redirect_callback' => false,
    'redirect_callback_url' => '',
    'download_folder' => 'box_download',
    'folder' => [
        'parent' => 0,
    ],
];