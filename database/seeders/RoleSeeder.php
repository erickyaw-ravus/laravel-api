<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(PermissionSeeder::class);

        Role::firstOrCreate(['name' => 'Super Admin']);
        $management = Role::firstOrCreate(['name' => 'Management']);
        $management->syncPermissions(
            Permission::whereIn('name', ['users.view', 'users.detail', 'users.create', 'users.edit', 'users.edit-role', 'roles.view', 'roles.view-with-permissions', 'roles.create', 'permissions.view'])->pluck('id')->all()
        );
        $resident = Role::firstOrCreate(['name' => 'Resident']);
        $resident->syncPermissions(
            Permission::whereIn('name', ['users.edit'])->pluck('id')->all()
        );
        Role::firstOrCreate(['name' => 'Security']);
    }
}
