<?php

namespace Database\Seeders;

use App\Enums\RateDays;
use App\Models\RoomType;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    /**
     * Seed sample room types, rooms and a weekend rate.
     *
     * Run with: php artisan db:seed --class=RoomSeeder
     */
    public function run(): void
    {
        $catalogue = [
            [
                'type' => [
                    'name' => 'Standard Room',
                    'description' => 'Double bed, private bathroom, and free Wi-Fi.',
                    'capacity' => 2,
                    'base_rate' => 1800,
                ],
                'rooms' => ['101' => 1, '102' => 1, '103' => 1, '104' => 1],
            ],
            [
                'type' => [
                    'name' => 'Deluxe Room',
                    'description' => 'More space, a work desk, and a quiet view.',
                    'capacity' => 3,
                    'base_rate' => 2600,
                ],
                'rooms' => ['201' => 2, '202' => 2, '203' => 2],
            ],
            [
                'type' => [
                    'name' => 'Family Suite',
                    'description' => 'Two rooms for families or longer stays.',
                    'capacity' => 5,
                    'base_rate' => 4200,
                ],
                'rooms' => ['301' => 3, '302' => 3],
            ],
        ];

        foreach ($catalogue as $entry) {
            $roomType = RoomType::firstOrCreate(['name' => $entry['type']['name']], $entry['type']);

            foreach ($entry['rooms'] as $number => $floor) {
                $roomType->rooms()->firstOrCreate(['number' => (string) $number], ['floor' => $floor]);
            }

            $roomType->rates()->firstOrCreate(
                ['name' => 'Weekend rate'],
                ['amount' => $roomType->base_rate * 1.2, 'applies_on' => RateDays::Weekends],
            );
        }
    }
}
