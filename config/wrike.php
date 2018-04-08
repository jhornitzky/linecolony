<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services your application utilizes. Set this in your ".env" file.
    |
    */

    'id' => env('WRIKE_CLIENT_ID'),
    'secret' => env('WRIKE_CLIENT_SECRET'),
    'redirect' => env('WRIKE_CLIENT_REDIRECT_URI', 'http://localhost:8000'),
];