<?php

namespace App\Modules\Memberships\Policies;

use App\Modules\Memberships\Models\MembershipPlan;
use App\Modules\People\Models\User;
use Illuminate\Auth\Access\Response;
use Laravel\Pennant\Feature;

class MembershipPlanPolicy
{
    public function before(User $user): ?Response
    {
        if ($user->isGlobalAdmin()) {
            return null;
        }

        $tenant = $user->tenantRoles()->first()?->tenant;
        if ($tenant && ! Feature::active('memberships', $tenant)) {
            return Response::denyAsNotFound('Memberships are not available for this center.');
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        if ($user->isGlobalAdmin()) {
            return true;
        }

        $role = $user->roleInCurrentTenant();

        return $role === 'ADMIN' || $role === 'EDITOR' || $role === 'VIEWER';
    }

    public function view(User $user, MembershipPlan $plan): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        if ($user->isGlobalAdmin()) {
            return true;
        }

        return $user->roleInCurrentTenant() === 'ADMIN' || $user->roleInCurrentTenant() === 'EDITOR';
    }

    public function update(User $user, MembershipPlan $plan): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, MembershipPlan $plan): bool
    {
        if ($user->isGlobalAdmin()) {
            return true;
        }

        return $user->roleInCurrentTenant() === 'ADMIN';
    }
}
