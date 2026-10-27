<?php

return [
    'groq_keys' => array_values(array_filter(array_map('trim', explode(',', (string) env('GROQ_API_KEYS', ''))))),
    'gemini_keys' => array_values(array_filter(array_map('trim', explode(',', (string) env('GEMINI_API_KEYS', ''))))),
    'groq_model' => env('GROQ_MODEL', 'llama-3.1-8b-instant'),
    'gemini_model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),

    'fcm' => [
        'project_id' => env('FCM_PROJECT_ID'),
        'service_account' => env('FCM_SERVICE_ACCOUNT_PATH', storage_path('app/firebase-service-account.json')),
        'channel_id' => 'urgent_needs_channel',
    ],

    'free_limits' => [
        'children' => 1,
        'pro_children' => 4,
        'family_members' => 2,
    ],
];
