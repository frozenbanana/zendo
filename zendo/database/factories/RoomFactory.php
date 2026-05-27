<?php

namespace Database\Factories;

use App\Modules\Lodging\Models\Building;
use App\Modules\Lodging\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoomFactory extends Factory
{
    protected $model = Room::class;

    public function definition(): array
    {
        return [
            'building_id' => Building::factory(),
            'name' => fake()->randomElement(['Room 101', 'Room 102', 'Room 103', 'Suite A', 'Suite B', 'Dormitory', 'Private Room', 'Double Room']),
            'capacity' => fake()->numberBetween(1, 8),
            'room_type' => fake()->randomElement(['single', 'double', 'dormitory', 'suite']),
        ];
    }
}
