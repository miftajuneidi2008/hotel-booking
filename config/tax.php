<?php

/*
| Tax and invoice settings, section 5.9.
|
| Business rule 5: taxes and service charge are settings (rate, on/off), not fixed in the code.
| Until the property settings screen (FR-38) exists, the owner sets them here or in the .env file,
| the same way as config/folio.php and config/payments.php.
|
| Prices on the guest bill already include service charge and VAT ("inclusive"). The invoice shows
| how much of the total is service charge and VAT, so the amount the guest pays never changes.
| Every invoice stores the rates and amounts it was issued with, so changing a rate here never
| changes an old invoice (business rule 2).
*/
return [

    // FR-33: who issues the invoice. Name, address, phone and email fall back to config/hotel.php.
    'business' => [
        'name' => env('TAX_BUSINESS_NAME'),
        'tin' => env('TAX_BUSINESS_TIN'),
        'address' => env('TAX_BUSINESS_ADDRESS'),
        'phone' => env('TAX_BUSINESS_PHONE'),
        'email' => env('TAX_BUSINESS_EMAIL'),
    ],

    'vat' => [
        'enabled' => (bool) env('TAX_VAT_ENABLED', true),
        'rate' => (float) env('TAX_VAT_RATE', 15),   // percent
    ],

    'service_charge' => [
        'enabled' => (bool) env('TAX_SERVICE_CHARGE_ENABLED', false),
        'rate' => (float) env('TAX_SERVICE_CHARGE_RATE', 10),   // percent
        // true: VAT is charged on the price plus the service charge. false: both are taken from the price.
        'vat_applies' => (bool) env('TAX_VAT_ON_SERVICE_CHARGE', true),
    ],

    // FR-34: invoice numbers are PREFIX + a running number, for example INV-000001. A number is never reused.
    'invoice' => [
        'prefix' => env('INVOICE_PREFIX', 'INV-'),
        'padding' => (int) env('INVOICE_NUMBER_PADDING', 6),
    ],

];
