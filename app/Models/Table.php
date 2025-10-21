<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Table extends Model
{
    use HasFactory;

    protected $fillable = [
        'table_number',
        'qr_code', 
        'status'
    ];

    // Relationships
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function currentOrder()
    {
        return $this->hasOne(Order::class)
                    ->where('payment_status', 'Paid')
                    ->where('session_expires_at', '>', now());
    }
}