<?php

namespace Database\Seeders;

use App\Enums\ChargeCategory;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;

/**
 * A starting menu with sample prices (ETB). Run it once, then change names and prices on the menu screen:
 *
 *     php artisan db:seed --class=MenuItemSeeder
 */
class MenuItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [ChargeCategory::Food, 'Breakfast', 250],
            [ChargeCategory::Food, 'Lunch', 300],
            [ChargeCategory::Food, 'Dinner', 350],
            [ChargeCategory::Food, 'Coffee (buna)', 40],
            [ChargeCategory::Food, 'Tea', 30],
            [ChargeCategory::Food, 'Soft drink', 50],
            [ChargeCategory::Food, 'Bottled water', 25],
            [ChargeCategory::Food, 'Beer', 90],
            [ChargeCategory::Laundry, 'Shirt', 80],
            [ChargeCategory::Laundry, 'Trousers', 100],
            [ChargeCategory::Laundry, 'Dress', 150],
            [ChargeCategory::Transport, 'Airport pickup', 800],
            [ChargeCategory::Transport, 'Taxi', 300],
            [ChargeCategory::Other, 'Late check-out', 500],
            [ChargeCategory::Other, 'Extra bed', 400],
        ];

        foreach ($items as [$category, $name, $price]) {
            MenuItem::query()->firstOrCreate(
                ['name' => $name],
                ['category' => $category, 'price' => $price, 'is_active' => true],
            );
        }
    }
}
