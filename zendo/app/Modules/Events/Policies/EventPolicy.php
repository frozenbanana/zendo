<?php

namespace App\Modules\Events\Policies;

use App\Modules\Events\Models\Event;
use App\Modules\People\Models\User;

class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->roleInCurrentTenant() !== null
            || $user->isGlobalAdmin();
    }

    public function view(User $user, Event $event): bool
    {
        if ($user->isGlobalAdmin()) {
            return true;
        }

        $role = $user->roleInCurrentTenant();

        return $role === 'ADMIN'
            || $role === 'EDITOR'
            || $role === 'VIEWER';
    }

    public function create(User $user): bool
    {
        if ($user->isGlobalAdmin()) {
            return true;
        }

        $role = $user->roleInCurrentTenant();

        return $role === 'ADMIN' || $role === 'EDITOR';
    }

    public function update(User $user, Event $event): bool
    {
        if ($user->isGlobalAdmin()) {
            return true;
        }

        $role = $user->roleInCurrentTenant();

        return $role === 'ADMIN' || $role === 'EDITOR';
    }

    public function delete(User $user, Event $event): bool
    {
        if ($user->isGlobalAdmin()) {
            return true;
        }

        return $user->roleInCurrentTenant() === 'ADMIN';
    }

    public function restore(User $user, Event $event): bool
    {
        if ($user->isGlobalAdmin()) {
            return true;
        }

        return $user->roleInCurrentTenant() === 'ADMIN';
    }

    public function forceDelete(User $user, Event $event): bool
    {
        if ($user->isGlobalAdmin()) {
            return true;
        }

        return $user->roleInCurrentTenant() === 'ADMIN';
    }
}
