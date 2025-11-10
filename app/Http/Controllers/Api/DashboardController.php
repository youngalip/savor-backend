<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * DashboardController
 * 
 * Handles owner dashboard analytics and metrics
 * - Today's revenue, orders, customers
 * - Monthly statistics with growth rate
 * - Top selling menus
 * - Revenue trends (30 days)
 * - Peak hours analysis
 * - Category breakdown
 */
class DashboardController extends Controller
{
    /**
     * Get dashboard overview with all metrics
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $data = [
                'today' => $this->getTodayMetrics(),
                'this_month' => $this->getMonthlyMetrics(),
                'top_menus' => $this->getTopMenus(),
                'revenue_chart' => $this->getRevenueChart(),
                'orders_by_hour' => $this->getOrdersByHour(),
                'category_breakdown' => $this->getCategoryBreakdown()
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load dashboard data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get today's metrics
     * 
     * @return array
     */
    private function getTodayMetrics()
    {
        $today = DB::table('orders')
            ->whereDate('created_at', Carbon::today())
            ->where('payment_status', 'Paid')
            ->selectRaw('
                COALESCE(SUM(total_amount), 0) as revenue,
                COUNT(*) as orders_count,
                COUNT(DISTINCT customer_id) as customers_count,
                COALESCE(AVG(total_amount), 0) as avg_order_value
            ')
            ->first();

        return [
            'revenue' => (float) $today->revenue,
            'orders_count' => (int) $today->orders_count,
            'customers_count' => (int) $today->customers_count,
            'avg_order_value' => (float) $today->avg_order_value
        ];
    }

    /**
     * Get monthly metrics with growth rate
     * 
     * @return array
     */
    private function getMonthlyMetrics()
    {
        $currentMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();

        // Current month data
        $currentData = DB::table('orders')
            ->where('created_at', '>=', $currentMonth)
            ->where('payment_status', 'Paid')
            ->selectRaw('
                COALESCE(SUM(total_amount), 0) as revenue,
                COUNT(*) as orders_count
            ')
            ->first();

        // Last month data
        $lastMonthData = DB::table('orders')
            ->whereBetween('created_at', [
                $lastMonth,
                $lastMonth->copy()->endOfMonth()
            ])
            ->where('payment_status', 'Paid')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as revenue')
            ->first();

        // Calculate growth rate
        $growthRate = 0;
        if ($lastMonthData->revenue > 0) {
            $growthRate = (($currentData->revenue - $lastMonthData->revenue) / $lastMonthData->revenue) * 100;
        }

        return [
            'revenue' => (float) $currentData->revenue,
            'orders_count' => (int) $currentData->orders_count,
            'growth_rate' => round($growthRate, 2)
        ];
    }

    /**
     * Get top 10 selling menus (last 30 days)
     * 
     * @return array
     */
    private function getTopMenus()
    {
        $menus = DB::table('order_items as oi')
            ->join('menus as m', 'oi.menu_id', '=', 'm.id')
            ->join('categories as c', 'm.category_id', '=', 'c.id')
            ->join('orders as o', 'oi.order_id', '=', 'o.id')
            ->where('o.payment_status', 'Paid')
            ->where('o.created_at', '>=', Carbon::now()->subDays(30))
            ->selectRaw('
                m.id as menu_id,
                m.name,
                c.name as category,
                SUM(oi.quantity) as total_sold,
                SUM(oi.subtotal) as revenue
            ')
            ->groupBy('m.id', 'm.name', 'c.name')
            ->orderByDesc('total_sold')
            ->limit(10)
            ->get();

        return $menus->map(function($menu) {
            return [
                'menu_id' => $menu->menu_id,
                'name' => $menu->name,
                'category' => $menu->category,
                'total_sold' => (int) $menu->total_sold,
                'revenue' => (float) $menu->revenue
            ];
        })->toArray();
    }

    /**
     * Get revenue chart data (last 30 days)
     * 
     * @return array
     */
    private function getRevenueChart()
    {
        $data = DB::table('orders')
            ->where('payment_status', 'Paid')
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->selectRaw('
                DATE(created_at) as date,
                COALESCE(SUM(total_amount), 0) as revenue
            ')
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->get();

        return $data->map(function($item) {
            return [
                'date' => $item->date,
                'revenue' => (float) $item->revenue
            ];
        })->toArray();
    }

    /**
     * Get orders by hour (today)
     * 
     * @return array
     */
    private function getOrdersByHour()
    {
        $data = DB::table('orders')
            ->whereDate('created_at', Carbon::today())
            ->where('payment_status', 'Paid')
            ->selectRaw('
                EXTRACT(HOUR FROM created_at) as hour,
                COUNT(*) as count
            ')
            ->groupBy('hour')
            ->orderBy('hour', 'ASC')
            ->get();

        return $data->map(function($item) {
            return [
                'hour' => (int) $item->hour,
                'count' => (int) $item->count
            ];
        })->toArray();
    }

    /**
     * Get category breakdown (this month)
     * 
     * @return array
     */
    private function getCategoryBreakdown()
    {
        $currentMonth = Carbon::now()->startOfMonth();

        // Get total revenue for percentage calculation
        $totalRevenue = DB::table('orders')
            ->where('created_at', '>=', $currentMonth)
            ->where('payment_status', 'Paid')
            ->sum('total_amount');

        $data = DB::table('order_items as oi')
            ->join('menus as m', 'oi.menu_id', '=', 'm.id')
            ->join('categories as c', 'm.category_id', '=', 'c.id')
            ->join('orders as o', 'oi.order_id', '=', 'o.id')
            ->where('o.payment_status', 'Paid')
            ->where('o.created_at', '>=', $currentMonth)
            ->selectRaw('
                c.name as category,
                COALESCE(SUM(oi.subtotal), 0) as revenue
            ')
            ->groupBy('c.name')
            ->orderByDesc('revenue')
            ->get();

        return $data->map(function($item) use ($totalRevenue) {
            $percentage = $totalRevenue > 0 
                ? round(($item->revenue / $totalRevenue) * 100, 2)
                : 0;

            return [
                'category' => $item->category,
                'revenue' => (float) $item->revenue,
                'percentage' => $percentage
            ];
        })->toArray();
    }
}