<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * ReportService
 * 
 * Handles complex analytics queries and report generation
 * - Revenue analysis
 * - Menu performance tracking
 * - Category breakdown
 * - Customer insights
 * - Peak hours analysis
 */
class ReportService
{
    /**
     * Get summary statistics for a date range
     * 
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array
     */
    public function getSummary(Carbon $startDate, Carbon $endDate): array
    {
        $currentData = DB::table('orders')
            ->where('payment_status', 'Paid')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                COALESCE(SUM(total_amount), 0) as total_revenue,
                COUNT(*) as total_orders,
                COUNT(DISTINCT customer_id) as total_customers,
                COALESCE(AVG(total_amount), 0) as avg_order_value
            ')
            ->first();

        // Calculate previous period for growth rate
        $daysDiff = $startDate->diffInDays($endDate) + 1;
        $previousStart = $startDate->copy()->subDays($daysDiff);
        $previousEnd = $startDate->copy()->subDay();
        
        $previousRevenue = $this->getTotalRevenue($previousStart, $previousEnd);

        $growthRate = 0;
        if ($previousRevenue > 0) {
            $growthRate = (($currentData->total_revenue - $previousRevenue) / $previousRevenue) * 100;
        }

        return [
            'total_revenue' => (float) $currentData->total_revenue,
            'total_orders' => (int) $currentData->total_orders,
            'total_customers' => (int) $currentData->total_customers,
            'avg_order_value' => (float) $currentData->avg_order_value,
            'growth_rate' => round($growthRate, 2)
        ];
    }

    /**
     * Get total revenue for a date range
     * 
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return float
     */
    public function getTotalRevenue(Carbon $startDate, Carbon $endDate): float
    {
        return (float) DB::table('orders')
            ->where('payment_status', 'Paid')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('total_amount');
    }

    /**
     * Get daily revenue breakdown
     * 
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array
     */
    public function getDailyBreakdown(Carbon $startDate, Carbon $endDate): array
    {
        $data = DB::table('orders')
            ->where('payment_status', 'Paid')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                DATE(created_at) as date,
                COALESCE(SUM(total_amount), 0) as revenue,
                COUNT(*) as orders,
                COUNT(DISTINCT customer_id) as customers,
                COALESCE(AVG(total_amount), 0) as avg_order_value
            ')
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->get();

        return $data->map(function($item) {
            return [
                'date' => $item->date,
                'revenue' => (float) $item->revenue,
                'orders' => (int) $item->orders,
                'customers' => (int) $item->customers,
                'avg_order_value' => (float) $item->avg_order_value
            ];
        })->toArray();
    }

    /**
     * Get weekly revenue breakdown
     * 
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array
     */
    public function getWeeklyBreakdown(Carbon $startDate, Carbon $endDate): array
    {
        $data = DB::table('orders')
            ->where('payment_status', 'Paid')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                EXTRACT(YEAR FROM created_at) as year,
                EXTRACT(WEEK FROM created_at) as week,
                COALESCE(SUM(total_amount), 0) as revenue,
                COUNT(*) as orders
            ')
            ->groupBy('year', 'week')
            ->orderBy('year', 'ASC')
            ->orderBy('week', 'ASC')
            ->get();

        return $data->map(function($item) {
            return [
                'year' => (int) $item->year,
                'week' => (int) $item->week,
                'revenue' => (float) $item->revenue,
                'orders' => (int) $item->orders
            ];
        })->toArray();
    }

    /**
     * Get monthly revenue breakdown
     * 
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array
     */
    public function getMonthlyBreakdown(Carbon $startDate, Carbon $endDate): array
    {
        $data = DB::table('orders')
            ->where('payment_status', 'Paid')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                EXTRACT(YEAR FROM created_at) as year,
                EXTRACT(MONTH FROM created_at) as month,
                COALESCE(SUM(total_amount), 0) as revenue,
                COUNT(*) as orders
            ')
            ->groupBy('year', 'month')
            ->orderBy('year', 'ASC')
            ->orderBy('month', 'ASC')
            ->get();

        return $data->map(function($item) {
            return [
                'year' => (int) $item->year,
                'month' => (int) $item->month,
                'revenue' => (float) $item->revenue,
                'orders' => (int) $item->orders
            ];
        })->toArray();
    }

    /**
     * Get category breakdown
     * 
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array
     */
    public function getCategoryBreakdown(Carbon $startDate, Carbon $endDate): array
    {
        $totalRevenue = $this->getTotalRevenue($startDate, $endDate);

        $data = DB::table('order_items as oi')
            ->join('menus as m', 'oi.menu_id', '=', 'm.id')
            ->join('categories as c', 'm.category_id', '=', 'c.id')
            ->join('orders as o', 'oi.order_id', '=', 'o.id')
            ->where('o.payment_status', 'Paid')
            ->whereBetween('o.created_at', [$startDate, $endDate])
            ->selectRaw('
                c.id as category_id,
                c.name as category_name,
                COALESCE(SUM(oi.subtotal), 0) as revenue,
                SUM(oi.quantity) as quantity_sold,
                COUNT(DISTINCT o.id) as orders
            ')
            ->groupBy('c.id', 'c.name')
            ->orderByDesc('revenue')
            ->get();

        return $data->map(function($item) use ($totalRevenue) {
            $percentage = $totalRevenue > 0 
                ? round(($item->revenue / $totalRevenue) * 100, 2)
                : 0;

            return [
                'category_id' => $item->category_id,
                'category_name' => $item->category_name,
                'revenue' => (float) $item->revenue,
                'quantity_sold' => (int) $item->quantity_sold,
                'orders' => (int) $item->orders,
                'percentage' => $percentage
            ];
        })->toArray();
    }

    /**
     * Get top selling menus
     * 
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param int $limit
     * @param string $sortBy 'revenue' or 'quantity'
     * @param int|null $categoryId
     * @return array
     */
    public function getTopMenus(
        Carbon $startDate, 
        Carbon $endDate, 
        int $limit = 10, 
        string $sortBy = 'quantity',
        ?int $categoryId = null
    ): array {
        $query = DB::table('order_items as oi')
            ->join('menus as m', 'oi.menu_id', '=', 'm.id')
            ->join('categories as c', 'm.category_id', '=', 'c.id')
            ->join('orders as o', 'oi.order_id', '=', 'o.id')
            ->where('o.payment_status', 'Paid')
            ->whereBetween('o.created_at', [$startDate, $endDate]);

        if ($categoryId) {
            $query->where('m.category_id', $categoryId);
        }

        $menus = $query->selectRaw('
                m.id as menu_id,
                m.name as menu_name,
                c.name as category,
                m.subcategory,
                SUM(oi.quantity) as quantity_sold,
                SUM(oi.subtotal) as revenue,
                AVG(oi.price) as avg_price,
                COUNT(DISTINCT oi.order_id) as orders_count
            ')
            ->groupBy('m.id', 'm.name', 'c.name', 'm.subcategory')
            ->orderByDesc($sortBy === 'revenue' ? 'revenue' : 'quantity_sold')
            ->limit($limit)
            ->get();

        return $menus->map(function($menu) {
            return [
                'menu_id' => $menu->menu_id,
                'menu_name' => $menu->menu_name,
                'category' => $menu->category,
                'subcategory' => $menu->subcategory,
                'quantity_sold' => (int) $menu->quantity_sold,
                'revenue' => (float) $menu->revenue,
                'avg_price' => (float) $menu->avg_price,
                'orders_count' => (int) $menu->orders_count
            ];
        })->toArray();
    }

    /**
     * Get peak hours analysis
     * 
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array
     */
    public function getPeakHours(Carbon $startDate, Carbon $endDate): array
    {
        $data = DB::table('orders')
            ->where('payment_status', 'Paid')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                EXTRACT(HOUR FROM created_at) as hour,
                COUNT(*) as orders_count,
                COALESCE(SUM(total_amount), 0) as revenue,
                COALESCE(AVG(total_amount), 0) as avg_order_value
            ')
            ->groupBy('hour')
            ->orderBy('hour', 'ASC')
            ->get();

        return $data->map(function($item) {
            return [
                'hour' => (int) $item->hour,
                'orders_count' => (int) $item->orders_count,
                'revenue' => (float) $item->revenue,
                'avg_order_value' => (float) $item->avg_order_value
            ];
        })->toArray();
    }

    /**
     * Get payment method breakdown
     * 
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array
     */
    public function getPaymentMethodBreakdown(Carbon $startDate, Carbon $endDate): array
    {
        $data = DB::table('orders')
            ->where('payment_status', 'Paid')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                CASE 
                    WHEN payment_reference IS NOT NULL THEN \'Online\'
                    ELSE \'Cash\'
                END as method,
                COUNT(*) as count,
                COALESCE(SUM(total_amount), 0) as amount
            ')
            ->groupBy('method')
            ->get();

        $total = $data->sum('amount');

        return $data->map(function($item) use ($total) {
            $percentage = $total > 0 
                ? round(($item->amount / $total) * 100, 2)
                : 0;

            return [
                'method' => $item->method,
                'count' => (int) $item->count,
                'amount' => (float) $item->amount,
                'percentage' => $percentage
            ];
        })->toArray();
    }

    /**
     * Get customer insights
     * 
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array
     */
    public function getCustomerInsights(Carbon $startDate, Carbon $endDate): array
    {
        $data = DB::table('orders as o')
            ->join('customers as c', 'o.customer_id', '=', 'c.id')
            ->where('o.payment_status', 'Paid')
            ->whereBetween('o.created_at', [$startDate, $endDate])
            ->selectRaw('
                COUNT(DISTINCT c.id) as unique_customers,
                COUNT(o.id) as total_orders,
                COALESCE(AVG(order_count.orders_per_customer), 0) as avg_orders_per_customer,
                COALESCE(SUM(o.total_amount), 0) as total_revenue,
                COALESCE(AVG(o.total_amount), 0) as avg_order_value
            ')
            ->crossJoin(DB::raw('(
                SELECT customer_id, COUNT(*) as orders_per_customer
                FROM orders
                WHERE payment_status = \'Paid\'
                AND created_at BETWEEN \'' . $startDate . '\' AND \'' . $endDate . '\'
                GROUP BY customer_id
            ) as order_count'))
            ->first();

        return [
            'unique_customers' => (int) $data->unique_customers,
            'total_orders' => (int) $data->total_orders,
            'avg_orders_per_customer' => round((float) $data->avg_orders_per_customer, 2),
            'total_revenue' => (float) $data->total_revenue,
            'avg_order_value' => (float) $data->avg_order_value,
            'avg_revenue_per_customer' => $data->unique_customers > 0 
                ? round($data->total_revenue / $data->unique_customers, 2)
                : 0
        ];
    }

    /**
     * Get table utilization stats
     * 
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array
     */
    public function getTableUtilization(Carbon $startDate, Carbon $endDate): array
    {
        $data = DB::table('orders as o')
            ->join('tables as t', 'o.table_id', '=', 't.id')
            ->where('o.payment_status', 'Paid')
            ->whereBetween('o.created_at', [$startDate, $endDate])
            ->selectRaw('
                t.id as table_id,
                t.table_number,
                t.zone,
                COUNT(o.id) as orders_count,
                COALESCE(SUM(o.total_amount), 0) as revenue
            ')
            ->groupBy('t.id', 't.table_number', 't.zone')
            ->orderByDesc('orders_count')
            ->get();

        return $data->map(function($item) {
            return [
                'table_id' => $item->table_id,
                'table_number' => $item->table_number,
                'zone' => $item->zone,
                'orders_count' => (int) $item->orders_count,
                'revenue' => (float) $item->revenue
            ];
        })->toArray();
    }

    /**
     * Get low stock items
     * 
     * @return array
     */
    public function getLowStockItems(): array
    {
        $data = DB::table('menus as m')
            ->join('categories as c', 'm.category_id', '=', 'c.id')
            ->whereRaw('m.stock_quantity <= m.minimum_stock')
            ->where('m.is_available', true)
            ->select(
                'm.id',
                'm.name',
                'c.name as category',
                'm.stock_quantity',
                'm.minimum_stock'
            )
            ->orderBy('m.stock_quantity', 'ASC')
            ->get();

        return $data->map(function($item) {
            return [
                'menu_id' => $item->id,
                'menu_name' => $item->name,
                'category' => $item->category,
                'current_stock' => (int) $item->stock_quantity,
                'minimum_stock' => (int) $item->minimum_stock,
                'deficit' => (int) ($item->minimum_stock - $item->stock_quantity)
            ];
        })->toArray();
    }

    /**
     * Get revenue trend with growth rate
     * 
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array
     */
    public function getRevenueTrend(Carbon $startDate, Carbon $endDate): array
    {
        $dailyData = $this->getDailyBreakdown($startDate, $endDate);

        // Calculate growth rate for each day
        $trendData = [];
        $previousRevenue = null;

        foreach ($dailyData as $index => $day) {
            $growthRate = 0;
            if ($previousRevenue !== null && $previousRevenue > 0) {
                $growthRate = (($day['revenue'] - $previousRevenue) / $previousRevenue) * 100;
            }

            $trendData[] = array_merge($day, [
                'growth_rate' => round($growthRate, 2)
            ]);

            $previousRevenue = $day['revenue'];
        }

        return $trendData;
    }
}