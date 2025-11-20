<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        // 🔥 FIX: Get user from auth guard instead of request
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Authentication required.'
            ], 401);
        }

        // Owner has ALL ACCESS to staff and owner routes
        if (strtolower($user->role) === 'owner') {
            return $next($request);
        }

        // 🔥 FIX: Normalize roles for comparison (case-insensitive)
        $userRole = strtolower($user->role);
        $allowedRoles = array_map('strtolower', $roles);

        // For non-owner users, check if their role is in allowed roles
        if (!in_array($userRole, $allowedRoles)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. You do not have permission to access this resource.',
                'required_roles' => $roles,
                'your_role' => $user->role
            ], 403);
        }

        return $next($request);
    }
}