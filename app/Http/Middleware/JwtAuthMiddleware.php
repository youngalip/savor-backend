<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class JwtAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        try {
            // Authenticate user via token
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 401);
            }

            // Check if user account is active
            if (!$user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account is disabled. Please contact administrator.'
                ], 403);
            }

            // 🔥 FIX: Set user to auth guard instead of merge
            auth()->setUser($user);

        } catch (TokenExpiredException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token has expired',
                'error_code' => 'TOKEN_EXPIRED'
            ], 401);

        } catch (TokenInvalidException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token is invalid',
                'error_code' => 'TOKEN_INVALID'
            ], 401);

        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token not provided',
                'error_code' => 'TOKEN_ABSENT'
            ], 401);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication failed',
                'error' => $e->getMessage()
            ], 500);
        }

        return $next($request);
    }
}