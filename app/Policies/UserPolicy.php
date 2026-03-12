<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Allow update if the authenticated user is a Super Admin (handled by Gate::before)
     * or is updating their own profile.
     */
    public function update(User $authenticatedUser, User $user): bool
    {
        return $authenticatedUser->id === $user->id;
    }
}
