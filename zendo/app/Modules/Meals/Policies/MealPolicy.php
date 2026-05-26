<?php

namespace App\Modules\Meals\Policies;

use App\Modules\People\Models\User;
use Illuminate\Auth\Access\HandlesRequests;
use Illuminate\Auth\Access\Response;

class MealPolicy
{
    use HandlesRequests;

    public function before(User $user, string $ability): ?Response
    {
        if (! $user->tenant->featureFlags()->meals()) {
            return Response::denyAsNotFound('Meals are not available for this center.');
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('view_meals');
    }

    public function view(User $user): bool
    {
        return $user->can('view_meals');
    }

    public function create(User $user): bool
    {
        return $user->can('create_meals');
    }

    public function update(User $user): bool
    {
        return $user->can('update_meals');
    }

    public function delete(User $user): bool
    {
        return $user->can('delete_meals');
    }
}
