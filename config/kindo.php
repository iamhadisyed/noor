<?php

return [
    'groq_keys' => array_values(array_filter(array_map('trim', explode(',', (string) env('GROQ_API_KEYS', ''))))),
    'gemini_keys' => array_values(array_filter(array_map('trim', explode(',', (string) env('GEMINI_API_KEYS', ''))))),
    // The owner's admin account (created by `php artisan db:seed`, or `php artisan kindo:install`). It signs in with email and a six-digit PIN.
    // Outside production the defaults below make a fresh install usable at once; IN PRODUCTION there is no default: set ADMIN_EMAIL and ADMIN_PIN,
    // or the seeder makes a random PIN and shows it once. Change the PIN any time with `php artisan kindo:set-pin <email> <pin>`.
    'admin' => [
        'email' => strtolower(trim((string) (env('ADMIN_EMAIL') ?: (env('APP_ENV') === 'production' ? '' : 'admin@kindo.local')))),
        'pin' => (string) (env('ADMIN_PIN') ?: (env('APP_ENV') === 'production' ? '' : '482916')),
    ],
    // Super admins are configured, never self-service. Comma-separated emails (the admin account above is always one of them).
    'super_admins' => array_values(array_unique(array_filter(array_map(fn ($e) => strtolower(trim($e)), array_merge(
        explode(',', (string) env('SUPER_ADMIN_EMAILS', '')),
        [(string) (env('ADMIN_EMAIL') ?: (env('APP_ENV') === 'production' ? '' : 'admin@kindo.local'))],
    ))))),

    // Making a parent Pro by hand: the parent taps "Make me Pro", we email how to pay, they send the payment details, the owner marks them paid.
    'payment' => [
        'contact_email' => env('PAYMENT_CONTACT_EMAIL', env('MAIL_FROM_ADDRESS', 'hello@example.com')),
        'instructions' => env('PAYMENT_INSTRUCTIONS', 'Please reply to this email and we will send you the payment details (bank transfer, JazzCash or Easypaisa).'),
        'default_days' => (int) env('PRO_DEFAULT_DAYS', 365),
    ],

    // Demo and owner accounts that sign in with a six-digit PIN (see database/seeders/AdminSeeder.php and DemoSeeder.php).
    // The public demo accounts: full Pro, no purchase, reset every night, and they cannot send email, use AI or delete the account.
    // On by default outside production; in production set DEMO_ENABLED=true (and choose your own PINs) if you want them.
    'demo' => [
        'enabled' => (bool) env('DEMO_ENABLED', env('APP_ENV') !== 'production'),
        'parent' => ['email' => 'demo.parent@kindo.local', 'pin' => (string) env('DEMO_PARENT_PIN', '135790')],
        'child' => ['email' => 'demo.child@kindo.local', 'pin' => (string) env('DEMO_CHILD_PIN', '246801')],
    ],

    'otp' => [
        'ttl_minutes' => 10,
        'max_attempts' => 5,
        'cooldown_seconds' => (int) env('KINDO_OTP_COOLDOWN', 60),
    ],
    'token_ttl_days' => 30,
    'backup' => ['password' => env('KINDO_BACKUP_PASSWORD'), 'keep' => (int) env('KINDO_BACKUP_KEEP', 7)],
    // Alerts that always get through, even in quiet hours or when muted.
    'urgent_needs' => ['potty', 'pain', 'help'],
    'specialists_max' => 5,   // therapists and teachers per child
    'household_max' => 3,   // people a parent can share their children with
    'admin_token_ttl_hours' => 12,

    'groq_model' => env('GROQ_MODEL', 'llama-3.1-8b-instant'),
    'groq_vision_model' => env('GROQ_VISION_MODEL', 'meta-llama/llama-4-scout-17b-16e-instruct'),
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
        'custom_cards_per_child' => 3,   // free plan: own picture cards per child; Pro is unlimited
        'lessons_per_child' => 3,   // free plan: custom lessons per child; Pro is unlimited
    ],
];
