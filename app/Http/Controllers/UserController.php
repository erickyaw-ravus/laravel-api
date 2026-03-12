<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserCollection;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    use AuthorizesRequests;
    /**
     * List users with pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);
        $perPage = min(max($perPage, 1), 100);

        $users = User::query()->orderBy('id')->paginate($perPage);

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
     * Create a new user. Only authenticated users can create another user.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = User::create($data);
        $user->assignRole('User');

        return ApiResponse::success(new UserResource($user), 'User created', Response::HTTP_CREATED);
    }

    /**
     * Update an existing user. Allowed for Super Admin (any user) or the user updating their own profile.
     * Only Super Admin can change the role; users updating themselves cannot.
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $data = $request->validated();

        // Only Super Admin can change roles; strip role when user is updating themselves
        if (array_key_exists('role', $data)) {
            if ($request->user()->hasRole('Super Admin')) {
                $user->syncRoles([$data['role']]);
            }
            unset($data['role']);
        }

        $user->fill($data);
        $user->save();

        return ApiResponse::success(new UserResource($user), 'User updated');
    }
}
