<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Planeward Community App
    |--------------------------------------------------------------------------
    |
    | Values from Settings › Apps › Developer in Planeward. Use one Planeward
    | app per environment (dev and production). Read these through config(),
    | never env(), so `php artisan config:cache` keeps working.
    |
    */

    // Must equal the viewer token's `aud`. One app id per deployment.
    'app_id' => env('PLANEWARD_APP_ID'),

    // App secret (pwa_...) used to call the Planeward API. Server only.
    'app_secret' => env('PLANEWARD_APP_SECRET'),

    // Signing secret for `x-planeward-signature`. Server only.
    'webhook_secret' => env('PLANEWARD_WEBHOOK_SECRET'),

    // Planeward deployment that issues viewer tokens and serves the API.
    'base_url' => rtrim(env('PLANEWARD_BASE_URL') ?: 'https://app.planeward.com', '/'),

];
