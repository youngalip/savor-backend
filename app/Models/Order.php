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
        'completed_at',
        'session_expires_at'
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'service_charge_rate' => 'decimal:4',
        'service_charge_amount' => 'decimal:2',
        'tax_rate' => 'decimal:4',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'completed_at' => 'datetime',
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

    /**
     * Get breakdown pricing
     */
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

    /**
     * Calculate and set pricing
     */
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

    /**
     * Static method untuk calculate pricing breakdown
     */
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

    /**
     * Check apakah order sudah dibayar
     */
    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    /**
     * Mark order sebagai paid
     */
    public function markAsPaid(string $paymentReference = null): void
    {
        $this->update([
            'payment_status' => 'paid',
            'payment_reference' => $paymentReference,
            'paid_at' => now(),
        ]);
    }

    /**
     * Check apakah semua item sudah selesai (Done)
     */
    public function isAllItemsCompleted(): bool
    {
        $totalItems = $this->items()->count();
        
        if ($totalItems === 0) {
            return false;
        }
        
        $completedItems = $this->items()
            ->where('status', 'Done')
            ->count();

        return $completedItems === $totalItems;
    }

    /**
     * Mark order sebagai completed
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'completed_at' => now(),
        ]);
    }

    /**
     * Auto mark as completed jika semua items Done
     */
    public function autoCompleteIfReady(): void
    {
        if ($this->isAllItemsCompleted() && !$this->completed_at) {
            $this->markAsCompleted();
        }
    }

    /**
     * Get items grouped by station type
     */
    public function getItemsByStation(): array
    {
        $items = $this->items()->with('menu.category')->get();

        $grouped = [
            'kitchen' => [],
            'bar' => [],
            'pastry' => [],
        ];

        foreach ($items as $item) {
            $station = $item->assigned_station;
            if ($station && isset($grouped[$station])) {
                $grouped[$station][] = $item;
            }
        }

        return $grouped;
    }

    /**
     * Get preparation progress percentage
     */
    public function getPreparationProgress(): array
    {
        $total = $this->items()->count();
        
        if ($total === 0) {
            return [
                'total' => 0,
                'pending' => 0,
                'done' => 0,
                'percentage' => 0,
            ];
        }

        $pending = $this->items()->where('status', 'Pending')->count();
        $done = $this->items()->where('status', 'Done')->count();

        return [
            'total' => $total,
            'pending' => $pending,
            'done' => $done,
            'percentage' => round(($done / $total) * 100, 2),
        ];
    }

    /**
     * Scope: Only paid orders
     */
    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    /**
     * Scope: Only completed orders
     */
    public function scopeCompleted($query)
    {
        return $query->whereNotNull('completed_at');
    }
}