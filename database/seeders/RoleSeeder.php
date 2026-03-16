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

        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin']);
        $user = Role::firstOrCreate(['name' => 'User']);

        // Super Admin gets all permissions (route-name based)
        $superAdmin->syncPermissions(Permission::pluck('id')->all());

        // User role: can edit own profile only (policy restricts to self)
        $user->syncPermissions(
            Permission::whereIn('name', ['users.edit'])->pluck('id')->all()
        );
    }
}
