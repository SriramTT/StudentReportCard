<?php

namespace App\Policies;

use App\Models\Term;
use App\Models\User;

class TermPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    public function view(User $user, Term $term): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    public function update(User $user, Term $term): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    public function delete(User $user, Term $term): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    public function reorder(User $user): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }
}
