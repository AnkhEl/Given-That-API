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

    'ai' => [
        'provider' => env('AI_PROVIDER', 'anthropic'), // 'anthropic' or 'openai'
        'api_key' => env('AI_API_KEY'),

        // Anthropic configuration
        'anthropic_model' => env('AI_ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),

        // OpenAI configuration
        'openai_model' => env('AI_OPENAI_MODEL', 'gpt-4-turbo-preview'),

        // GPT4All configuration (local)
        'gpt4all_url' => env('AI_GPT4ALL_URL', 'http://localhost:4891'),
        'gpt4all_model' => env('AI_GPT4ALL_MODEL', 'mistral-7b-instruct-v0.2.Q4_0.gguf'),
    ],

];
