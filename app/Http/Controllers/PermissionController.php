<?php

namespace App\Http\Controllers;

use App\Http\Resources\PermissionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    /**
     * List all permissions (no pagination).
     */
    public function index(): JsonResponse
    {
        $permissions = Permission::query()->orderBy('id')->get();

        return ApiResponse::success(PermissionResource::collection($permissions));
    }
}
