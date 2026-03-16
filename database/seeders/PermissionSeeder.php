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
            'users.view',
            'users.detail',
            'users.create',
            'users.edit',
            'users.edit-role',
            'roles.view',
            'roles.create',
            'permissions.view',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name]);
        }
    }
}
