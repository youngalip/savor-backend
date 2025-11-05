<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Services\StationAssignmentService;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'menu_id',
        'quantity',
        'price',
        'subtotal', 
        'status',
        'special_notes'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'quantity' => 'integer',
    ];

    // Relationships
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }

    /**
     * Get kategori dari menu
     */
    public function getMenuCategoryAttribute()
    {
        return $this->menu?->category;
    }

    /**
     * Get station type yang akan memproses item ini
     * 
     * @return string|null kitchen|bar|pastry
     */
    public function getAssignedStationAttribute(): ?string
    {
        if (!$this->menu || !$this->menu->category) {
            return null;
        }

        return StationAssignmentService::getStationFromCategory(
            $this->menu->category->name
        );
    }

    /**
     * Check apakah item sudah selesai diproses
     * 
     * @return bool
     */
    public function isCompleted(): bool
    {
        return strtolower($this->status) === 'done';
    }

    /**
     * Check apakah item masih pending
     * 
     * @return bool
     */
    public function isPending(): bool
    {
        return strtolower($this->status) === 'pending';
    }

    /**
     * Mark item sebagai done
     * 
     * @return bool
     */
    public function markAsDone(): bool
    {
        $this->status = 'Done';
        return $this->save();
    }

    /**
     * Mark item sebagai pending
     * 
     * @return bool
     */
    public function markAsPending(): bool
    {
        $this->status = 'Pending';
        return $this->save();
    }

    /**
     * Scope: Filter by station type
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $stationType kitchen|bar|pastry
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByStation($query, string $stationType)
    {
        $categoryIds = StationAssignmentService::getCategoryIdsByStation($stationType);
        
        return $query->whereHas('menu', function ($q) use ($categoryIds) {
            $q->whereIn('category_id', $categoryIds);
        });
    }

    /**
     * Scope: Filter by status
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $status Pending|Done
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', ucfirst(strtolower($status)));
    }

    /**
     * Scope: Only from paid orders
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFromPaidOrders($query)
    {
        return $query->whereHas('order', function ($q) {
            $q->where('payment_status', 'paid');
        });
    }
}