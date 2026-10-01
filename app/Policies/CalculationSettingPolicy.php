<?php

namespace App\Policies;

use App\Models\CalculationSetting;
use App\Models\User;

class CalculationSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    public function view(User $user, CalculationSetting $calculationSetting): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    public function update(User $user, CalculationSetting $calculationSetting): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }

    public function delete(User $user, CalculationSetting $calculationSetting): bool
    {
        return $user->isAdmin() || $user->isOfficeStaff();
    }
}
