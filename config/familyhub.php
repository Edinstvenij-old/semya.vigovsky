<?php

return [

    /*
    | Allow anyone who opens the site to create a new family. Keep it false in
    | production: the first family is created with `php artisan family:create`,
    | everyone else joins through invitation links.
    */
    'allow_registration' => env('FAMILYHUB_ALLOW_REGISTRATION', false),

    // Disk for photos, scans and boarding passes: "local" (private) or "s3".
    'files_disk' => env('FAMILYHUB_FILES_DISK', 'local'),

    // Disk for family backups.
    'backup_disk' => env('FAMILYHUB_BACKUP_DISK', 'local'),

    'max_upload_kb' => (int) env('FAMILYHUB_MAX_UPLOAD_KB', 15360),

    // How long a 2FA confirmation unlocks the documents module (minutes).
    'two_factor_ttl' => (int) env('FAMILYHUB_2FA_TTL', 720),

    'invitation_ttl_hours' => (int) env('FAMILYHUB_INVITE_TTL_HOURS', 72),

    /*
    | Optional HTTP Basic auth in front of the whole site (second lock in
    | addition to the app login, keeps crawlers and strangers out entirely).
    | Prefer doing this in nginx; this is a fallback for simple hosting.
    */
    'basic_auth' => [
        'user' => env('FAMILYHUB_BASIC_AUTH_USER'),
        'password' => env('FAMILYHUB_BASIC_AUTH_PASSWORD'),
    ],

    /*
    | Telegram bot for urgent notifications (fallback for phones where browser push
    | is unreliable). Create a bot with @BotFather, put token and username here,
    | then run `php artisan telegram:webhook`.
    */
    'telegram' => [
        'token' => env('TELEGRAM_BOT_TOKEN'),
        'username' => env('TELEGRAM_BOT_USERNAME'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
    ],

    // Live flight status (AeroDataBox via RapidAPI).
    'flights' => [
        'api_key' => env('FLIGHT_STATUS_API_KEY'),
    ],

    // Air-raid alerts from alerts.in.ua (request a free token on their site).
    'alerts' => [
        'token' => env('ALERTS_IN_UA_TOKEN'),
    ],

    /*
    | Realtime (Laravel Reverb). Where BROWSERS connect; the server itself talks to
    | Reverb via REVERB_HOST/REVERB_PORT (in Docker: the internal "reverb" service).
    */
    'realtime' => [
        'host' => env('REVERB_PUBLIC_HOST', env('REVERB_HOST', 'localhost')),
        'port' => (int) env('REVERB_PUBLIC_PORT', env('REVERB_PORT', 8080)),
        'scheme' => env('REVERB_PUBLIC_SCHEME', env('REVERB_SCHEME', 'http')),
    ],

    // Web Push (VAPID). Generate with `php artisan push:vapid`.
    'vapid' => [
        'subject' => env('VAPID_SUBJECT', 'mailto:admin@example.com'),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
    ],
];
