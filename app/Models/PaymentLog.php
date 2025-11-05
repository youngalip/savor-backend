<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaymentLog extends Model
{
    use HasFactory;

    public $timestamps = false; // hanya ada created_at

    protected $fillable = [
        'order_id',
        'amount',
        'transaction_id',
        'status',
        'response_data'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'response_data' => 'json',
        'created_at' => 'datetime',
    ];

    // Relationships
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}