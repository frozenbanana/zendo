<?php

namespace Database\Factories;

use App\Modules\Memberships\Models\MembershipPlan;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class MembershipPlanFactory extends Factory
{
    protected $model = MembershipPlan::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->randomElement(['Basic', 'Standard', 'Premium', 'Founder', 'Community']),
            'description' => fake()->sentence(),
            'price_cents' => fake()->numberBetween(5000, 50000),
            'billing_cycle' => fake()->randomElement(['monthly', 'quarterly', 'yearly']),
            'is_active' => true,
        ];
    }
}
