<?php

namespace Database\Factories;

use App\Modules\People\Models\User;
use App\Modules\People\Models\UserTenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserTenantRoleFactory extends Factory
{
    protected $model = UserTenantRole::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tenant_id' => Tenant::factory(),
            'role' => fake()->randomElement(['ADMIN', 'EDITOR', 'VIEWER']),
        ];
    }
}
