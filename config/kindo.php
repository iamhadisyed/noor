<?php

return [
    'groq_keys' => array_values(array_filter(array_map('trim', explode(',', (string) env('GROQ_API_KEYS', ''))))),
    'gemini_keys' => array_values(array_filter(array_map('trim', explode(',', (string) env('GEMINI_API_KEYS', ''))))),
    // Super admins are configured, never self-service. Comma-separated emails.
    'super_admins' => array_values(array_filter(array_map(fn ($e) => strtolower(trim($e)), explode(',', (string) env('SUPER_ADMIN_EMAILS', ''))))),

    'otp' => [
        'ttl_minutes' => 10,
        'max_attempts' => 5,
        'cooldown_seconds' => 60,
    ],
    'token_ttl_days' => 30,
    'admin_token_ttl_hours' => 12,

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
        'lessons_per_child' => 3,   // free plan: custom lessons per child; Pro is unlimited
    ],
];
