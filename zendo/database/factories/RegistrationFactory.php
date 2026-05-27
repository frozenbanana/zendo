<?php

namespace Database\Factories;

use App\Modules\Events\Models\Event;
use App\Modules\Registration\Enums\RegistrationStatus;
use App\Modules\Registration\Models\Registration;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class RegistrationFactory extends Factory
{
    protected $model = Registration::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'event_id' => Event::factory(),
            'guest_first_name' => fake()->firstName(),
            'guest_last_name' => fake()->lastName(),
            'guest_email' => fake()->unique()->safeEmail(),
            'guest_phone' => fake()->optional()->phoneNumber(),
            'status' => fake()->randomElement(RegistrationStatus::cases()),
            'total_cents' => fake()->numberBetween(5000, 30000),
        ];
    }
}
