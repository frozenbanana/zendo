<?php

namespace Database\Factories;

use App\Modules\Lodging\Models\Building;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class BuildingFactory extends Factory
{
    protected $model = Building::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->randomElement(['Main Hall', 'Lotus House', 'Cedar Lodge', 'River Cabin', 'Pine House', 'Sunset Cottage', 'Oakhaven', 'The Barn']),
            'description' => fake()->sentence(),
            'address' => fake()->address(),
        ];
    }
}
