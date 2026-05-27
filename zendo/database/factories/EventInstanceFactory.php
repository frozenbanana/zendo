<?php

namespace Database\Factories;

use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventInstance;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventInstanceFactory extends Factory
{
    protected $model = EventInstance::class;

    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('+1 week', '+3 months');

        return [
            'event_id' => Event::factory(),
            'title' => fake()->randomElement(['Morning Session', 'Afternoon Workshop', 'Evening Circle', 'Full Day Retreat', 'Weekend Immersion']),
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify('+3 hours'),
            'capacity' => fake()->numberBetween(10, 60),
            'price_override_cents' => null,
        ];
    }
}
