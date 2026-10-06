<?php

// Ported from the old Next.js app's src/lib/site-config.ts — same defaults,
// overridable via .env on the Hostinger server.
return [
    'name' => env('SITE_NAME', 'আমারশপ'),
    'legal_name' => env('SITE_LEGAL_NAME', 'Amar Shop in BD'),
    'url' => env('SITE_URL', 'https://online.amarshopinbd.com'),
    'support_phone' => env('SUPPORT_PHONE', '+880 1874-783819'),
    'support_phone_raw' => env('SUPPORT_PHONE_RAW', '+8801874783819'),
    'whatsapp_number' => env('WHATSAPP_NUMBER', '8801874783819'),
    'support_email' => env('SUPPORT_EMAIL', 'support@amarshopinbd.com'),
    'address' => env('STORE_ADDRESS', 'ঢাকা, বাংলাদেশ'),
    // Default theme colors — must match the :root values in resources/css/app.css.
    // Overridden per site from Site Setting → Colors.
    'colors' => [
        'brand' => '#f97316',
        'secondary' => '#111827',
        'accent' => '#fbbf24',
        'background' => '#f9fafb',
        'text' => '#1f2937',
        'success' => '#16a34a',
        'error' => '#ef4444',
    ],
    'social' => [
        'facebook' => env('FACEBOOK_URL', ''),
        'youtube' => env('YOUTUBE_URL', ''),
        'instagram' => env('INSTAGRAM_URL', ''),
        'tiktok' => env('TIKTOK_URL', ''),
    ],
];
