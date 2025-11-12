<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

/**
 * AuthController
 * 
 * Handles authentication endpoints:
 * - Login
 * - Logout
 * - Refresh token
 * - Get current user
 */
class AuthController extends Controller
{
    /**
     * Auth service instance
     */
    private AuthService $authService;

    /**
     * Constructor
     */
    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Login endpoint
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        // Validation
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:8'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Get user using Eloquent model
            $user = User::where('email', $request->email)->first();

            // Check if user exists
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            // Check if account is active
            if (!$user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account is disabled. Please contact administrator.'
                ], 403);
            }

            // Verify password
            if (!Hash::check($request->password, $user->password)) {
                // Log failed attempt
                $this->authService->logFailedLogin(
                    $user->id,
                    $request->ip(),
                    $request->userAgent()
                );

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            // Generate JWT token using User model
            $token = JWTAuth::fromUser($user);

            // Log successful login
            $this->authService->logLogin(
                $user->id,
                $request->ip(),
                $request->userAgent()
            );

            // Get redirect URL based on role
            $redirectUrl = $this->authService->getRedirectUrl($user->role);

            // Return response
            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => config('jwt.ttl') * 60, // Convert minutes to seconds
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                        'is_active' => $user->is_active
                    ],
                    'redirect_url' => $redirectUrl
                ]
            ]);

        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Could not create token',
                'error' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Logout endpoint
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        try {
            // Get user from token
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Log logout
            $this->authService->logLogout($user->id);

            // Note: We don't invalidate token (no blacklist)
            // Token will expire naturally after TTL
            // Client should delete token from localStorage

            return response()->json([
                'success' => true,
                'message' => 'Logout successful'
            ]);

        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to logout',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Refresh token endpoint
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh(Request $request)
    {
        try {
            // Get new token
            $newToken = JWTAuth::parseToken()->refresh();

            // Get user from new token
            $user = JWTAuth::setToken($newToken)->authenticate();

            // Check if user is still active
            if (!$user || !$user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account is disabled'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'message' => 'Token refreshed successfully',
                'data' => [
                    'token' => $newToken,
                    'token_type' => 'Bearer',
                    'expires_in' => config('jwt.ttl') * 60
                ]
            ]);

        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Could not refresh token',
                'error' => $e->getMessage()
            ], 401);
        }
    }

    /**
     * Get current authenticated user
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function me(Request $request)
    {
        try {
            // Get user from token
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Check if still active
            if (!$user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account is disabled'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'is_active' => $user->is_active,
                    'created_at' => $user->created_at
                ]
            ]);

        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token invalid',
                'error' => $e->getMessage()
            ], 401);
        }
    }
}