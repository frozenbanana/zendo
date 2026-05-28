<?php

namespace App\Modules\Lodging\Policies;

use App\Modules\Lodging\Models\Building;
use App\Modules\People\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\Response;
use Laravel\Pennant\Feature;

class BuildingPolicy
{
    public function before(User $user): ?Response
    {
        $tenant = Filament::getTenant() ?? $user->tenantRoles()->first()?->tenant;

        if ($tenant && ! Feature::active('lodging', $tenant)) {
            return Response::denyAsNotFound('Lodging is not available for this center.');
        }

        if ($user->isGlobalAdmin()) {
            return null;
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

    public function view(User $user, Building $building): bool
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

    public function update(User $user, Building $building): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Building $building): bool
    {
        if ($user->isGlobalAdmin()) {
            return true;
        }

        return $user->roleInCurrentTenant() === 'ADMIN';
    }
}
