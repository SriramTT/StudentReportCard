<?php

namespace App\Policies;

use App\Models\ReportConfiguration;
use App\Models\User;

class ReportConfigurationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    public function view(User $user, ReportConfiguration $reportConfiguration): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    public function update(User $user, ReportConfiguration $reportConfiguration): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    public function delete(User $user, ReportConfiguration $reportConfiguration): bool
    {
        return $user->isAdmin();
    }
}
