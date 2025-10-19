<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Gmail API Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your Gmail API settings for sending emails
    | through Google's Gmail API using OAuth 2.0 authentication.
    |
    */

    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'refresh_token' => env('GOOGLE_REFRESH_TOKEN'),
    'redirect_uri' => env('GOOGLE_REDIRECT_URI', 'http://localhost:8000/auth/google/callback'),

    /*
    |--------------------------------------------------------------------------
    | Default Email Settings
    |--------------------------------------------------------------------------
    |
    | Configure default settings for emails sent through Gmail API
    |
    */

    'from_email' => env('GMAIL_FROM_EMAIL', 'noreply@ateaseattle.com'),
    'from_name' => env('GMAIL_FROM_NAME', 'ATEA Seattle'),

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Enable logging of Gmail API activities
    |
    */

    'enable_logging' => env('GMAIL_ENABLE_LOGGING', true),
    'log_file' => storage_path('logs/gmail.log'),

    /*
    |--------------------------------------------------------------------------
    | Gmail API Scopes
    |--------------------------------------------------------------------------
    |
    | The OAuth 2.0 scopes required for Gmail API access
    |
    */

    'scopes' => [
        'https://www.googleapis.com/auth/gmail.send',
        'https://www.googleapis.com/auth/gmail.compose'
    ],

];