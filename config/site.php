<?php

// Ported from the old Next.js app's src/lib/site-config.ts — same defaults,
// overridable via .env on the Hostinger server.
return [
    'name' => env('SITE_NAME', 'আমারশপ'),
    'legal_name' => env('SITE_LEGAL_NAME', 'Amar Shop in BD'),
    'url' => env('SITE_URL', 'https://online.amarshopinbd.com'),
    'support_phone' => env('SUPPORT_PHONE', '+880 1700-000000'),
    'support_phone_raw' => env('SUPPORT_PHONE_RAW', '01700000000'),
    'whatsapp_number' => env('WHATSAPP_NUMBER', '8801700000000'),
    'support_email' => env('SUPPORT_EMAIL', 'support@amarshopinbd.com'),
    'address' => env('STORE_ADDRESS', 'ঢাকা, বাংলাদেশ'),
    'social' => [
        'facebook' => env('FACEBOOK_URL', 'https://facebook.com'),
        'youtube' => env('YOUTUBE_URL', 'https://youtube.com'),
        'instagram' => env('INSTAGRAM_URL', 'https://instagram.com'),
    ],
];
