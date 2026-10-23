<?php

return [

    /*
    | Main navigation. Each item needs a `label` and either a named `route` or a `url`.
    | Optional `auth` => true shows the item only to logged-in users; `can` => 'gate-name' only to users who pass that gate.
    | Optional `active`: URL pattern(s) that highlight the item (see Request::is()).
    | Links that contain a #anchor are never highlighted, unless `active` is set.
    */
    'links' => [
        ['label' => 'Home',      'url' => '/'],
        ['label' => 'Rooms',     'route' => 'rooms'],
        ['label' => 'Amenities', 'url' => '/#amenities'],
        ['label' => 'Contact',   'url' => '/#contact'],
        ['label' => 'My bookings', 'route' => 'my-bookings.index', 'auth' => true],
        ['label' => 'Front desk',  'route' => 'staff.bookings.index', 'can' => 'manage-bookings'],
    ],

    /*
    | Call-to-action button (accepts `route` or `url`). Set to null to hide it.
    */
    'cta' => ['label' => 'Book now', 'route' => 'booking'],

];
