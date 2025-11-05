<?php

namespace App\Services;

use App\Models\StationOrder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PastryService
{
    private const STATION_TYPE = 'pastry';

    /**
     * Get daftar pesanan pastry berdasarkan status
     */
    public function getOrders(string $status = 'pending'): Collection
    {
        return StationOrder::with([
                'order',
                'orderItem.menu.category'
            ])
            ->byStation(self::STATION_TYPE)
            ->byStatus($status)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Get semua pesanan pastry (semua status)
     */
    public function getAllOrders(): Collection
    {
        return StationOrder::with([
                'order',
                'orderItem.menu.category'
            ])
            ->byStation(self::STATION_TYPE)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get detail pesanan
     */
    public function getOrderDetail(int $id): ?StationOrder
    {
        return StationOrder::with([
                'order.customer',
                'order.table',
                'orderItem.menu.category'
            ])
            ->byStation(self::STATION_TYPE)
            ->find($id);
    }

    /**
     * Mulai mengerjakan pesanan
     */
    public function startOrder(int $id): StationOrder
    {
        return DB::transaction(function () use ($id) {
            $stationOrder = StationOrder::byStation(self::STATION_TYPE)
                ->findOrFail($id);

            $stationOrder->update([
                'status' => 'in_progress',
                'started_at' => now(),
            ]);

            Log::info("Pastry order {$id} started");

            return $stationOrder->fresh();
        });
    }

    /**
     * Selesaikan pesanan
     */
    public function completeOrder(int $id): StationOrder
    {
        return DB::transaction(function () use ($id) {
            $stationOrder = StationOrder::byStation(self::STATION_TYPE)
                ->findOrFail($id);

            $stationOrder->update([
                'status' => 'done',
                'completed_at' => now(),
            ]);

            Log::info("Pastry order {$id} completed");

            // Check apakah semua item dalam order sudah selesai
            $order = $stationOrder->order;
            if ($order->isAllStationsCompleted()) {
                $order->markAsCompleted();
                Log::info("Order #{$order->order_number} semua station selesai, status changed to completed");
            }

            return $stationOrder->fresh();
        });
    }

    /**
     * Get statistik pastry
     */
    public function getStatistics(): array
    {
        return [
            'pending' => StationOrder::byStation(self::STATION_TYPE)->pending()->count(),
            'in_progress' => StationOrder::byStation(self::STATION_TYPE)->inProgress()->count(),
            'done_today' => StationOrder::byStation(self::STATION_TYPE)
                ->done()
                ->whereDate('completed_at', today())
                ->count(),
        ];
    }
}