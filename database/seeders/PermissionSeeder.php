<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        if (Permission::count() > 0) {
            $this->command->info('Permissions already exist. Skipping.');
            return;
        }

        $roles = Role::all();
        if ($roles->count() == 0) {
            $this->command->warn('No roles found. Skipping.');
            return;
        }

        $this->command->info('Seeding permissions...');

        $permissionSets = [
            'Admin' => ['assets' => ['view', 'create', 'edit', 'delete'], 'users' => ['view', 'create', 'edit', 'delete'], 'work_orders' => ['view', 'create', 'edit', 'delete'], 'inventory' => ['view', 'create', 'edit', 'delete']],
            'Manager' => ['assets' => ['view', 'create', 'edit'], 'users' => ['view'], 'work_orders' => ['view', 'create', 'edit'], 'inventory' => ['view', 'create', 'edit']],
            'User' => ['assets' => ['view'], 'work_orders' => ['view', 'create'], 'inventory' => ['view']],
        ];

        foreach ($roles as $role) {
            Permission::create([
                'role_id' => $role->id,
                'permissions' => json_encode($permissionSets[$role->name] ?? $permissionSets['User']),
            ]);
        }

        $this->command->info('Created permissions for ' . $roles->count() . ' roles.');
    }
}
