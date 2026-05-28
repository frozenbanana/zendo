<?php

namespace Database\Factories;

use App\Modules\Events\Enums\EventStatus;
use App\Modules\Events\Models\Event;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'status' => EventStatus::Published->value,
            'starts_at' => now()->addDays(7),
            'ends_at' => now()->addDays(10),
            'capacity' => fake()->numberBetween(20, 200),
            'price_cents' => fake()->numberBetween(5000, 50000),
            'is_published' => true,
        ];
    }
}
