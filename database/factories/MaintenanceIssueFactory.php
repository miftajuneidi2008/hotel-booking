<?php

namespace Database\Factories;

use App\Enums\IssuePriority;
use App\Models\MaintenanceIssue;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceIssue>
 */
class MaintenanceIssueFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'room_id' => Room::factory(),
            'description' => fake()->sentence(4),
            'priority' => IssuePriority::Normal,
            'blocks_room' => false,
        ];
    }
}
