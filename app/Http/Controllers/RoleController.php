<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Resources\RoleResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;

class RoleController extends Controller
{
    /**
     * List all roles (no pagination). Super Admin only.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request): JsonResponse
    {
        $roles = Role::query()->orderBy('id')->get();

        return ApiResponse::success(RoleResource::collection($roles));
    }

    /**
     * Create a new role. Super Admin only.
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        $guardName = config('auth.defaults.guard', 'web');
        $role = Role::create([
            'name' => $request->validated('name'),
            'guard_name' => $guardName,
        ]);

        Log::info('Role created', [
            'role_id' => $role->id,
            'role_name' => $role->name,
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::success(new RoleResource($role), 'Role created', Response::HTTP_CREATED);
    }
}
