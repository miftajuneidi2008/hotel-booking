<?php

/*
| Restaurant settings.
|
| A hotel guest who orders at a table (through the receptionist or the cashier) holds that table until the
| staff free it from the New order screen. If nobody does, the hold ends by itself after this many hours since
| the guest's last order at that table, so a forgotten table never stays blocked. A walk-in customer's table is
| free as soon as the bill is paid, voided or moved to a room bill.
*/
return [

    'table_hold_hours' => env('RESTAURANT_TABLE_HOLD_HOURS', 4),

];
