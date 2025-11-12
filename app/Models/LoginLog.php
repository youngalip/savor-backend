<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * LoginLog Model
 * 
 * Tracks user login/logout activity
 */
class LoginLog extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'login_logs';

    /**
     * Disable updated_at timestamp (we only have login_at/logout_at)
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'ip_address',
        'user_agent',
        'login_at',
        'logout_at',
        'success'
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'login_at' => 'datetime',
        'logout_at' => 'datetime',
        'success' => 'boolean'
    ];

    /**
     * Get the user that owns the login log
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope: Get only successful logins
     */
    public function scopeSuccessful($query)
    {
        return $query->where('success', true);
    }

    /**
     * Scope: Get only failed logins
     */
    public function scopeFailed($query)
    {
        return $query->where('success', false);
    }

    /**
     * Scope: Get active sessions (logged in but not logged out)
     */
    public function scopeActive($query)
    {
        return $query->whereNull('logout_at')->where('success', true);
    }

    /**
     * Check if session is still active
     */
    public function isActive(): bool
    {
        return $this->success && is_null($this->logout_at);
    }
}