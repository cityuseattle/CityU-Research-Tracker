<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Admins and coordinators can list users.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->hasAnyRole(['admin', 'coordinator']);
    }

    /**
     * Admins and coordinators can view any user profile.
     */
    public function view(User $actor, User $target): bool
    {
        if ($actor->hasRole('admin')) return true;
        if ($actor->hasRole('coordinator')) return true;
        return $actor->id === $target->id;
    }

    /**
     * Admins and coordinators can create users.
     */
    public function create(User $actor): bool
    {
        return $actor->hasAnyRole(['admin', 'coordinator']);
    }

    /**
     * Admins can update anyone.
     * Coordinators can update others (only org/org_role/is_active — enforced in controller).
     * Users can update themselves.
     */
    public function update(User $actor, User $target): bool
    {
        if ($actor->hasRole('admin')) return true;
        if ($actor->hasRole('coordinator') && $actor->id !== $target->id) return true;
        return $actor->id === $target->id;
    }

    /**
     * Admins and coordinators can activate/deactivate users (not self).
     */
    public function delete(User $actor, User $target): bool
    {
        return $actor->hasAnyRole(['admin', 'coordinator']) && $actor->id !== $target->id;
    }

    /**
     * Only admins can assign/change roles.
     */
    public function assignRoles(User $actor): bool
    {
        return $actor->hasRole('admin');
    }

    /**
     * Only admins can reset another user's password.
     */
    public function resetPassword(User $actor, User $target): bool
    {
        return $actor->hasRole('admin') && $actor->id !== $target->id;
    }
}
