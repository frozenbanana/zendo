<?php

namespace Database\Factories;

use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(),
            'name' => fake()->company(),
            'description' => fake()->sentence(),
            'logo' => null,
            'custom_domain' => null,
            'features' => [],
            'registration_mode' => 'MANUAL_REVIEW',
            'currency' => 'EUR',
            'timezone' => 'Europe/Paris',
            'locale' => 'en',
            'is_active' => true,
        ];
    }
}
