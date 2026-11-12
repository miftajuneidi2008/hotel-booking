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

    // Test keys start with CHASECK_TEST-, live keys with CHASECK-.
    'secret_key' => env('CHAPA_SECRET_KEY'),

    // The "secret hash" typed in the Chapa dashboard (Settings > Webhooks). Used to check webhook calls.
    'webhook_secret' => env('CHAPA_WEBHOOK_SECRET'),

    'base_url' => env('CHAPA_BASE_URL', 'https://api.chapa.co/v1'),

    // Seconds to wait for Chapa before giving up.
    'timeout' => 20,

];
