<?php

/*
| Guest bill (folio) settings, section 5.4.
|
| Business rule 3: discounts and voids above a limit set by the owner need approval from a
| manager or the owner. Until the property settings screen (FR-38) exists, the owner sets
| the limits here or in the .env file. Set a limit to null to turn that check off.
*/
return [

    'discount_approval' => [
        // A discount of more than this share of the bill (room + extras) needs approval.
        'percent' => env('FOLIO_DISCOUNT_APPROVAL_PERCENT', 10),
        // A discount of more than this amount (ETB) needs approval.
        'amount' => env('FOLIO_DISCOUNT_APPROVAL_AMOUNT', 1000),
    ],

    // Voiding an extra charge of more than this amount (ETB) needs approval.
    'void_approval_amount' => env('FOLIO_VOID_APPROVAL_AMOUNT', 500),

    // When restaurant orders push a guest's unpaid balance (ETB) over this amount, the front desk gets a
    // notification, so the bill is no surprise at check-out. Set to null to turn the alert off.
    'room_charge_alert_amount' => env('FOLIO_ROOM_CHARGE_ALERT_AMOUNT', 5000),

];
