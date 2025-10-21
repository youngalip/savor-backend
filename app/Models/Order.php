<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_uuid',
        'order_number', 
        'customer_id',
        'table_id',
        'total_amount',
        'payment_status',
        'payment_reference',
        'notes',
        'paid_at',
        'session_expires_at'
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'session_expires_at' => 'datetime',
    ];

    // Relationships
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function table()
    {
        return $this->belongsTo(Table::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function paymentLogs()
    {
        return $this->hasMany(PaymentLog::class);
    }
}