<?php

namespace App\Policies;

use App\Models\SchoolSetting;
use App\Models\User;

class SchoolSettingPolicy
{
    /**
     * Determine whether the user can view school settings (Administrator and Office Staff).
     */
     public function viewAny(User $user): bool
     {
         return $user->isAdmin() || $user->isOfficeStaff();
     }

     /**
      * Determine whether the user can view a specific school setting (Administrator and Office Staff).
      */
     public function view(User $user, ?SchoolSetting $setting = null): bool
     {
         return $user->isAdmin() || $user->isOfficeStaff();
     }

     /**
      * Determine whether the user can create school settings (Administrator and Office Staff).
      */
     public function create(User $user): bool
     {
         return $user->isAdmin() || $user->isOfficeStaff();
     }

     /**
      * Determine whether the user can update school settings (Administrator and Office Staff).
      */
     public function update(User $user, ?SchoolSetting $setting = null): bool
     {
         return $user->isAdmin() || $user->isOfficeStaff();
     }

     /**
      * Determine whether the user can delete school settings (Administrator only).
      */
     public function delete(User $user, ?SchoolSetting $setting = null): bool
     {
         return $user->isAdmin();
     }
}
