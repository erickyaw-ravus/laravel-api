<?php

namespace App\Http\Controllers;

use App\Http\Resources\PermissionResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;

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
