<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Resources\RoleResource;
use App\Http\Responses\ApiResponse;
use App\Services\RoleService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class RoleController extends Controller
{
    public function __construct(
        private readonly RoleService $roleService
    ) {}

    /**
     * List all roles (no pagination).
     */
    public function index(): JsonResponse
    {
        $roles = $this->roleService->list();

        return ApiResponse::success(RoleResource::collection($roles));
    }

    /**
     * Create a new role.
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = $this->roleService->store($request->validated('name'), $request->user());

        return ApiResponse::success(new RoleResource($role), 'Role created', Response::HTTP_CREATED);
    }
}
