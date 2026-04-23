<?php

namespace App\Policies;

use App\Models\Program;
use App\Models\User;

class ProgramPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // Any authenticated user can list programs
    }

    public function view(User $user, Program $program): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'coordinator']);
    }

    public function update(User $user, Program $program): bool
    {
        return $user->hasAnyRole(['admin', 'coordinator']);
    }

    public function delete(User $user, Program $program): bool
    {
        return $user->hasAnyRole(['admin', 'coordinator']);
    }
}
