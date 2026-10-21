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

    // Web Push (VAPID). Generate with `php artisan push:vapid`.
    'vapid' => [
        'subject' => env('VAPID_SUBJECT', 'mailto:admin@example.com'),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
    ],
];
