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
        'subtotal',
        'service_charge_rate',
        'service_charge_amount',
        'tax_rate',
        'tax_amount',
        'total_amount',
        'payment_status',
        'payment_reference',
        'notes',
        'paid_at',
        'session_expires_at'
    ];

    protected $casts = [
        // TAMBAHKAN INI:
        'subtotal' => 'decimal:2',
        'service_charge_rate' => 'decimal:4',
        'service_charge_amount' => 'decimal:2',
        'tax_rate' => 'decimal:4',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'session_expires_at' => 'datetime',
    ];

    protected $appends = ['breakdown'];

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

    public function getBreakdownAttribute(): array
    {
        return [
            'subtotal' => (float) $this->subtotal,
            'service_charge' => [
                'rate' => (float) $this->service_charge_rate,
                'amount' => (float) $this->service_charge_amount,
                'percentage' => round($this->service_charge_rate * 100, 2) . '%'
            ],
            'tax' => [
                'rate' => (float) $this->tax_rate,
                'amount' => (float) $this->tax_amount,
                'percentage' => round($this->tax_rate * 100, 2) . '%'
            ],
            'total' => (float) $this->total_amount
        ];
    }

    // Calculate pricing
    public function calculatePricing(float $itemsSubtotal, ?float $serviceChargeRate = null, ?float $taxRate = null): void
    {
        if ($serviceChargeRate === null || $taxRate === null) {
            $rates = \App\Models\Setting::getPricingRates();
            $serviceChargeRate = $serviceChargeRate ?? $rates['service_charge_rate'];
            $taxRate = $taxRate ?? $rates['tax_rate'];
        }

        $serviceChargeAmount = round($itemsSubtotal * $serviceChargeRate, 2);
        $taxBase = $itemsSubtotal + $serviceChargeAmount;
        $taxAmount = round($taxBase * $taxRate, 2);
        $totalAmount = $taxBase + $taxAmount;

        $this->subtotal = $itemsSubtotal;
        $this->service_charge_rate = $serviceChargeRate;
        $this->service_charge_amount = $serviceChargeAmount;
        $this->tax_rate = $taxRate;
        $this->tax_amount = $taxAmount;
        $this->total_amount = $totalAmount;
    }

    // Static calculate helper
    public static function calculatePricingBreakdown(float $itemsSubtotal, ?float $serviceChargeRate = null, ?float $taxRate = null): array
    {
        if ($serviceChargeRate === null || $taxRate === null) {
            $rates = \App\Models\Setting::getPricingRates();
            $serviceChargeRate = $serviceChargeRate ?? $rates['service_charge_rate'];
            $taxRate = $taxRate ?? $rates['tax_rate'];
        }

        $serviceChargeAmount = round($itemsSubtotal * $serviceChargeRate, 2);
        $taxBase = $itemsSubtotal + $serviceChargeAmount;
        $taxAmount = round($taxBase * $taxRate, 2);
        $totalAmount = $taxBase + $taxAmount;

        return [
            'subtotal' => $itemsSubtotal,
            'service_charge' => [
                'rate' => $serviceChargeRate,
                'amount' => $serviceChargeAmount,
                'percentage' => round($serviceChargeRate * 100, 2) . '%'
            ],
            'tax' => [
                'rate' => $taxRate,
                'amount' => $taxAmount,
                'percentage' => round($taxRate * 100, 2) . '%'
            ],
            'total' => $totalAmount
        ];
    }
}