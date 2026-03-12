<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserCollection;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly UserService $userService
    ) {}

    /**
     * List users with pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);
        $users = $this->userService->list($perPage);

        return ApiResponse::successPaginated(UserCollection::make($users));
    }

    /**
     * Show a single user.
     */
    public function show(User $user): JsonResponse
    {
        return ApiResponse::success(new UserResource($user));
    }

    /**
     * Create a new user.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->userService->store($request->validated(), $request->user());

        return ApiResponse::success(new UserResource($user), 'User created', Response::HTTP_CREATED);
    }

    /**
     * Update an existing user. Super Admin can change role; users can update their own profile.
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $user = $this->userService->update($user, $request->validated(), $request->user());

        return ApiResponse::success(new UserResource($user), 'User updated');
    }
}
