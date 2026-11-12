<?php

/*
| Chapa online payment for guests who book on the website.
|
| Only bookings made online (source "online") can be paid through Chapa. Bookings made at the desk,
| by phone or by an agent keep using the normal payment forms for staff (cash, bank transfer, ...).
|
| Set the keys in .env. Leave CHAPA_SECRET_KEY empty (or set CHAPA_ENABLED=false) and the "Pay online"
| button simply does not appear, so the site behaves exactly as before.
*/
return [

    'enabled' => (bool) env('CHAPA_ENABLED', true),

    // Chapa API v2 PRIVATE key (server side only). Test keys start with CHAPA_TEST_, live keys with CHAPA_LIVE_.
    // The public key (CHAPA_*_PUB_...) is NOT accepted by the hosted checkout endpoint.
    'secret_key' => env('CHAPA_SECRET_KEY'),

    // The webhook secret you set in the Chapa dashboard. Chapa signs every webhook body with it
    // (HMAC SHA256, header x-chapa-signature).
    'webhook_secret' => env('CHAPA_WEBHOOK_SECRET'),

    // v2 uses one host for test and live. The key decides the environment.
    'base_url' => env('CHAPA_BASE_URL', 'https://api.chapa.global/v2'),

    // Seconds to wait for Chapa before giving up.
    'timeout' => 20,

];
