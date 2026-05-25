<?php

namespace App\Modules\Tenancy\Policies;

use App\Modules\People\Models\User;
use App\Modules\Tenancy\Models\Tenant;

class TenantPolicy
{
    public function update(User $user, Tenant $tenant): bool
    {
        if ($user->isGlobalAdmin()) {
            return true;
        }

        return $user->roleInTenant($tenant->id) === 'ADMIN';
    }

    public function manageUsers(User $user, Tenant $tenant): bool
    {
        if ($user->isGlobalAdmin()) {
            return true;
        }

        return $user->roleInTenant($tenant->id) === 'ADMIN';
    }
}
