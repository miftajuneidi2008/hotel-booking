<?php

return [

    // Limits for the booking forms.
    'booking' => [
        'max_nights' => 30,
        'max_guests' => 10,
    ],

    'rooms' => [
        [
            'slug' => 'standard',
            'name' => 'Standard Room',
            'description' => 'Double bed, private bathroom, and free Wi-Fi.',
            'price' => 1800, // ETB per night (sample)
            'image' => 'images/rooms/standard.jpg',
            'tag' => null,
        ],
        [
            'slug' => 'deluxe',
            'name' => 'Deluxe Room',
            'description' => 'More space, a work desk, and a quiet view.',
            'price' => 2600,
            'image' => 'images/rooms/deluxe.jpg',
            'tag' => 'Popular',
        ],
        [
            'slug' => 'family-suite',
            'name' => 'Family Suite',
            'description' => 'Two rooms for families or longer stays.',
            'price' => 4200,
            'image' => 'images/rooms/suite.jpg',
            'tag' => null,
        ],
    ],

    // "icon" is an SVG path (24x24, outline style)
    'amenities' => [
        ['title' => 'Free Wi-Fi', 'text' => 'Fast internet in every room and in the lobby.',
            'icon' => 'M8.3 15a5.25 5.25 0 017.4 0M5.1 11.9a9.75 9.75 0 0113.8 0M1.9 8.7a14.25 14.25 0 0120.2 0M12 19h.01'],
        ['title' => 'Breakfast', 'text' => 'Start the day with a fresh local or continental breakfast.',
            'icon' => 'M6 7h11v5a5 5 0 01-5 5h-1a5 5 0 01-5-5V7zM17 9h1.5a2.5 2.5 0 010 5H17M9 2v2M13 2v2'],
        ['title' => 'Airport pickup', 'text' => 'We can arrange a ride when you arrive.',
            'icon' => 'M5 13l1.5-4.5A2 2 0 018.4 7h7.2a2 2 0 011.9 1.5L19 13M4 13h16v4H4zM7 17v2M17 17v2'],
        ['title' => 'Parking', 'text' => 'Safe parking for guests with a car.',
            'icon' => 'M12 22a10 10 0 100-20 10 10 0 000 20zM10 17V7h3.5a3 3 0 010 6H10'],
        ['title' => '24/7 reception', 'text' => 'Someone is always here to help, day or night.',
            'icon' => 'M12 22a10 10 0 100-20 10 10 0 000 20zM12 7v5l3 2'],
        ['title' => 'Laundry', 'text' => 'Clean clothes during longer stays.',
            'icon' => 'M8 3L3 6l2 4 3-1v11h8V9l3 1 2-4-5-3a4 4 0 01-8 0z'],
    ],

    'contact' => [
        'address' => 'Your street, Your city, Ethiopia',
        'phone' => '+251 900 000 000',
        'email' => 'info@example.com',
    ],

];
