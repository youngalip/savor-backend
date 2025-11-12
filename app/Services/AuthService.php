<?php

namespace App\Services;

use App\Models\LoginLog;
use Illuminate\Support\Facades\DB;

/**
 * AuthService
 * 
 * Handles authentication business logic
 * - Login activity logging
 * - Session management
 */
class AuthService
{
    /**
     * Log successful login
     * 
     * @param int $userId
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @return LoginLog
     */
    public function logLogin(int $userId, ?string $ipAddress, ?string $userAgent): LoginLog
    {
        return LoginLog::create([
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'login_at' => now(),
            'success' => true
        ]);
    }

    /**
     * Log failed login attempt
     * 
     * @param int $userId
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @return LoginLog
     */
    public function logFailedLogin(int $userId, ?string $ipAddress, ?string $userAgent): LoginLog
    {
        return LoginLog::create([
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'login_at' => now(),
            'success' => false
        ]);
    }

    /**
     * Log logout
     * 
     * @param int $userId
     * @return bool
     */
    public function logLogout(int $userId): bool
    {
        // Update the most recent active login log
        return LoginLog::where('user_id', $userId)
            ->whereNull('logout_at')
            ->where('success', true)
            ->orderBy('login_at', 'desc')
            ->limit(1)
            ->update(['logout_at' => now()]);
    }

    /**
     * Get active sessions for user
     * 
     * @param int $userId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getActiveSessions(int $userId)
    {
        return LoginLog::where('user_id', $userId)
            ->active()
            ->orderBy('login_at', 'desc')
            ->get();
    }

    /**
     * Get login history for user
     * 
     * @param int $userId
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getLoginHistory(int $userId, int $limit = 10)
    {
        return LoginLog::where('user_id', $userId)
            ->orderBy('login_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get currently active users (who have active sessions)
     * 
     * @return array
     */
    public function getActiveUsers(): array
    {
        return DB::table('login_logs')
            ->join('users', 'login_logs.user_id', '=', 'users.id')
            ->select('users.id', 'users.name', 'users.email', 'users.role', 'login_logs.login_at')
            ->whereNull('login_logs.logout_at')
            ->where('login_logs.success', true)
            ->where('users.is_active', true)
            ->orderBy('login_logs.login_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get redirect URL based on user role
     * 
     * @param string $role
     * @return string
     */
    public function getRedirectUrl(string $role): string
    {
        $baseUrl = env('FRONTEND_URL', 'http://localhost:5173');
        
        $redirectMap = [
            'Kitchen' => $baseUrl . '/staff/kitchen-station',
            'Bar' => $baseUrl . '/staff/bar-station',
            'Pastry' => $baseUrl . '/staff/pastry-station',
            'Kasir' => $baseUrl . '/staff/cashier',
            'Owner' => $baseUrl . '/owner/dashboard',
        ];

        return $redirectMap[$role] ?? $baseUrl;
    }
    /**
     * Clean up old login logs (optional - for maintenance)
     * 
     * @param int $daysToKeep
     * @return int Number of deleted records
     */
    public function cleanupOldLogs(int $daysToKeep = 90): int
    {
        return LoginLog::where('login_at', '<', now()->subDays($daysToKeep))
            ->delete();
    }
}