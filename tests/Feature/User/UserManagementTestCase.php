<?php

namespace Tests\Feature\User;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class UserManagementTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    protected function actingAsSuperAdmin(): string
    {
        $user = User::factory()->twoFactorDisabled()->create();
        $user->assignRole('Super Admin');

        return $user->createToken('test')->plainTextToken;
    }

    protected function actingAsRegularUser(): string
    {
        $user = User::factory()->twoFactorDisabled()->create();
        $user->assignRole('User');

        return $user->createToken('test')->plainTextToken;
    }

    protected function authHeader(string $token): array
    {
        return ['Authorization' => 'Bearer ' . $token];
    }
}
