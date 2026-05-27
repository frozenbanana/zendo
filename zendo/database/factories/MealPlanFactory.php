<?php

namespace Database\Factories;

use App\Modules\Meals\Models\MealPlan;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class MealPlanFactory extends Factory
{
    protected $model = MealPlan::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->randomElement(['Full Board', 'Half Board', 'Breakfast Only', 'Vegetarian Full Board', 'Vegan Meal Plan', 'Lunch & Dinner']),
            'description' => fake()->sentence(),
            'price_cents' => fake()->numberBetween(2000, 15000),
            'is_available' => true,
        ];
    }
}
