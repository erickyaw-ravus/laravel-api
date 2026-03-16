<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\UserService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    private UserService $userService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->userService = app(UserService::class);
    }

    public function test_list_returns_paginated_users_ordered_by_id(): void
    {
        User::factory()->count(5)->create();

        $result = $this->userService->list(2);

        $this->assertSame(2, $result->perPage());
        $this->assertSame(5, $result->total());
        $this->assertCount(2, $result->items());
        $ids = array_map(fn ($user) => $user->id, $result->items());
        $this->assertSame($ids, array_values($ids)); // order preserved
    }

    public function test_list_clamps_per_page_to_min_one(): void
    {
        User::factory()->count(3)->create();

        $result = $this->userService->list(0);

        $this->assertSame(1, $result->perPage());
        $this->assertSame(3, $result->total());
        $this->assertCount(1, $result->items()); // first page has 1 item when per_page is 1
    }

    public function test_list_clamps_per_page_to_max_100(): void
    {
        User::factory()->count(150)->create();

        $result = $this->userService->list(200);

        $this->assertSame(100, $result->perPage());
        $this->assertCount(100, $result->items());
    }

    public function test_store_creates_user_and_assigns_user_role(): void
    {
        $creator = User::factory()->create();
        $creator->assignRole('User');

        $data = [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'Password123!',
        ];

        $user = $this->userService->store($data, $creator);

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'name' => 'New User',
        ]);
        $this->assertTrue($user->hasRole('User'));
    }

    public function test_update_updates_user_fields(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);
        $user->assignRole('User');
        $updater = User::factory()->create();
        $updater->assignRole('User');

        $updated = $this->userService->update($user, [
            'name' => 'New Name',
            'email' => 'new@example.com',
        ], $updater);

        $this->assertSame('New Name', $updated->name);
        $this->assertSame('new@example.com', $updated->email);
        $updated->refresh();
        $this->assertSame('New Name', $updated->name);
        $this->assertSame('new@example.com', $updated->email);
    }

    public function test_update_never_changes_role_even_if_role_in_data(): void
    {
        $user = User::factory()->create(['name' => 'Alice']);
        $user->assignRole('User');
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('Super Admin');

        $updated = $this->userService->update($user, [
            'name' => 'Alice Updated',
            'roles' => ['Super Admin'],
        ], $superAdmin);

        $this->assertSame('Alice Updated', $updated->name);
        $updated->refresh();
        $this->assertTrue($updated->hasRole('User'));
        $this->assertFalse($updated->hasRole('Super Admin'));
    }

    public function test_update_role_succeeds_when_updater_is_super_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('User');
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('Super Admin');

        $updated = $this->userService->updateRole($user, ['Super Admin'], $superAdmin);

        $updated->refresh();
        $this->assertTrue($updated->hasRole('Super Admin'));
        $this->assertFalse($updated->hasRole('User'));
    }

    /**
     * Authorization for role updates is enforced by the permission middleware
     * (users.edit-role). The service does not throw 403; see UpdateUserRoleTest
     * for HTTP-level 403 when a non-authorized user hits the endpoint.
     */
    public function test_update_role_assigns_role_regardless_of_caller(): void
    {
        $user = User::factory()->create();
        $user->assignRole('User');
        $updater = User::factory()->create();
        $updater->assignRole('User');

        $updated = $this->userService->updateRole($user, ['Super Admin'], $updater);

        $updated->refresh();
        $this->assertTrue($updated->hasRole('Super Admin'));
    }
}
