<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'device_id', 
        'session_token',
        'email',
        'user_agent',
        'ip_address',
        'last_activity',
        'table_id',
    ];

    protected $casts = [
        'last_activity' => 'datetime',
    ];

    // Relationships
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}