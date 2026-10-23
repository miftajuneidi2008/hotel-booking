<?php

/*
| Payments settings, section 5.5.
|
| Until the property settings screen (FR-38) exists, the owner sets these here or in the .env file.
*/
return [

    // Everything on the bill is in this currency.
    'currency' => 'ETB',

    // FR-22: other currencies the property accepts, for example PAYMENTS_FOREIGN_CURRENCIES=USD,EUR.
    // Leave it empty if the property only takes birr. The staff member types the exchange rate
    // (ETB for 1 unit) for every payment, because the rate changes every day.
    'foreign_currencies' => array_values(array_filter(array_map(
        'strtoupper',
        array_map('trim', explode(',', (string) env('PAYMENTS_FOREIGN_CURRENCIES', ''))),
    ))),

    // A refund of more than this amount (ETB) needs a manager or the owner. Set to null to turn the check off.
    'refund_approval_amount' => env('PAYMENTS_REFUND_APPROVAL_AMOUNT', 1000),

    // The daily cash summary closes the day at midnight in this timezone, whatever the app timezone is.
    'timezone' => env('PAYMENTS_TIMEZONE', 'Africa/Addis_Ababa'),

];
