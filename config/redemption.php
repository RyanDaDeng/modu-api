<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Redemption Code API Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration is used for the third-party API that creates
    | redemption codes. The API key should be set in your .env file.
    |
    */

    'api_key' => env('REDEMPTION_API_KEY', 'your-secret-api-key-here'),
];
