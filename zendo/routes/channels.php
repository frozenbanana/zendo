<?php

use App\Modules\People\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('tenant.{slug}', function (User $user, string $slug) {
    return $user->tenantRoles()
        ->whereHas('tenant', function ($query) use ($slug) {
            $query->where('slug', $slug);
        })
        ->exists() || $user->isGlobalAdmin();
});
