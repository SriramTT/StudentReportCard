<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'id' => 1,
                'name' => 'Administrator',
                'description' => 'Full system access including user management, configuration, and audit log viewing',
                'is_active' => true,
            ],
            [
                'id' => 2,
                'name' => 'Office Staff',
                'description' => 'Student management, data entry, and report generation',
                'is_active' => true,
            ],
            [
                'id' => 3,
                'name' => 'Subject Teacher',
                'description' => 'Mark entry and viewing for assigned subjects',
                'is_active' => true,
            ],
            [
                'id' => 4,
                'name' => 'Class Teacher',
                'description' => 'Mark entry and viewing for all subjects in assigned class/section',
                'is_active' => true,
            ],
        ];

        foreach ($roles as $roleData) {
            Role::query()->updateOrCreate(
                ['id' => $roleData['id']],
                [
                    'name' => $roleData['name'],
                    'description' => $roleData['description'],
                    'is_active' => $roleData['is_active'],
                ]
            );
        }

        // Synchronize identity sequence for roles table in PostgreSQL
        DB::statement("
            SELECT setval(
                pg_get_serial_sequence('roles', 'id'),
                COALESCE((SELECT MAX(id) FROM roles), 1)
            );
        ");
    }
}
