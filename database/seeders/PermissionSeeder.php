<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Permission names match route names for routes using middleware('permission') (EnsureRoutePermission).
        // Only routes in that group are checked; user, logout, password.change are auth-only and need no permission.
        $permissions = [
            'users.view' => 'List users',
            'users.detail' => 'View user details',
            'users.create' => 'Create users',
            'users.edit' => 'Edit users',
            'users.edit-role' => 'Edit user roles',
            'roles.view' => 'List roles',
            'roles.view-with-permissions' => 'List roles with their permissions',
            'roles.create' => 'Create roles',
            'permissions.view' => 'List permissions',
        ];

        foreach ($permissions as $name => $description) {
            Permission::updateOrCreate(['name' => $name], ['description' => $description]);
        }
    }
}
