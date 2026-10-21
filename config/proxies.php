<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | Read from .env here (not with env() in the code) so the value survives
    | `php artisan config:cache`, which stops .env from being read. Used by
    | App\Support\TrustedProxies and applied in AppServiceProvider::boot().
    |
    | empty = loopback and private networks only; "1.2.3.4,10.0.0.0/8" = those;
    | "*" = trust whoever connects (only when the host is always behind its own
    | proxy — see DEPLOY.md).
    */

    'trusted' => env('TRUSTED_PROXIES', ''),

];
