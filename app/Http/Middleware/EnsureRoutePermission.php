<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRoutePermission
{
    /**
     * Require the authenticated user to have a permission matching the current route name.
     * Route name is used as the permission name (e.g. users.view -> permission "users.view").
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $routeName = $request->route()?->getName();
        if ($routeName === null || $routeName === '') {
            return $next($request);
        }

        $user = $request->user();
        if (! $user) {
            return ApiResponse::error('Unauthenticated.', Response::HTTP_UNAUTHORIZED);
        }

        if ($user->hasRole('Super Admin')) {
            return $next($request);
        }

        if (! $user->hasPermissionTo($routeName)) {
            return ApiResponse::error('You do not have permission to perform this action.', Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
