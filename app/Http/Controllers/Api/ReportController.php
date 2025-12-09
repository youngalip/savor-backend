<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

/**
 * ReportController
 * 
 * Handles complex sales reports and analytics
 * - Overview report with date range
 * - Revenue analysis
 * - Menu performance tracking
 * - Peak hours analysis
 * - Payment method breakdown
 * - Export to CSV/Excel
 */
class ReportController extends Controller
{
    /**
     * Get overview report
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function overview(Request $request)
    {
        // Validation
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'group_by' => 'in:day,week,month'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $endDate = Carbon::parse($request->end_date)->endOfDay();
            $daysCount = $startDate->diffInDays($endDate) + 1;

            $data = [
                'period' => [
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'days_count' => $daysCount
                ],
                'summary' => $this->getSummary($startDate, $endDate),
                'daily_breakdown' => $this->getDailyBreakdown($startDate, $endDate),
                'category_breakdown' => $this->getCategoryBreakdownByDate($startDate, $endDate),
                'top_menus' => $this->getTopMenusByDate($startDate, $endDate),
                'payment_methods' => $this->getPaymentMethodBreakdown($startDate, $endDate)
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate overview report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get revenue report
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function revenue(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'category_id' => 'nullable|exists:categories,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $endDate = Carbon::parse($request->end_date)->endOfDay();
            $categoryId = $request->category_id;

            if ($categoryId) {
                // ✅ Per category dengan proportional tax/service
                $revenueData = DB::table('orders as o')
                    ->join(DB::raw("(
                        SELECT 
                            oi.order_id,
                            SUM(oi.subtotal) as category_subtotal
                        FROM order_items oi
                        JOIN menus m ON oi.menu_id = m.id
                        WHERE m.category_id = {$categoryId}
                        GROUP BY oi.order_id
                    ) as cat_items"), 'o.id', '=', 'cat_items.order_id')
                    ->where('o.payment_status', 'Paid')
                    ->whereBetween('o.created_at', [$startDate, $endDate])
                    ->selectRaw('
                        DATE(o.created_at) as date,
                        COALESCE(SUM(
                            cat_items.category_subtotal * (o.total_amount / o.subtotal)
                        ), 0) as revenue,
                        COUNT(DISTINCT o.id) as orders_count,
                        COUNT(DISTINCT o.customer_id) as customers_count,
                        COALESCE(AVG(
                            cat_items.category_subtotal * (o.total_amount / o.subtotal)
                        ), 0) as avg_order_value
                    ')
                    ->groupBy('date')
                    ->orderBy('date', 'ASC')
                    ->get();
            } else {
                // ✅ All categories - use total_amount
                $revenueData = DB::table('orders')
                    ->where('payment_status', 'Paid')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->selectRaw('
                        DATE(created_at) as date,
                        COALESCE(SUM(total_amount), 0) as revenue,
                        COUNT(*) as orders_count,
                        COUNT(DISTINCT customer_id) as customers_count,
                        COALESCE(AVG(total_amount), 0) as avg_order_value
                    ')
                    ->groupBy('date')
                    ->orderBy('date', 'ASC')
                    ->get();
            }

            // Calculate growth rate
            $previousPeriod = $this->getPreviousPeriodRevenue(
                $startDate->copy()->subDays($startDate->diffInDays($endDate) + 1),
                $startDate->copy()->subDay(),
                $categoryId
            );

            $currentTotal = $revenueData->sum('revenue');
            $growthRate = 0;
            if ($previousPeriod > 0) {
                $growthRate = (($currentTotal - $previousPeriod) / $previousPeriod) * 100;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'period' => [
                        'start_date' => $startDate->format('Y-m-d'),
                        'end_date' => $endDate->format('Y-m-d')
                    ],
                    'total_revenue' => (float) $currentTotal,
                    'growth_rate' => round($growthRate, 2),
                    'daily_data' => $revenueData->map(function($item) {
                        return [
                            'date' => $item->date,
                            'revenue' => (float) $item->revenue,
                            'orders_count' => (int) $item->orders_count,
                            'customers_count' => (int) $item->customers_count,
                            'avg_order_value' => (float) $item->avg_order_value
                        ];
                    })
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate revenue report',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    /**
     * Get menu performance report
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function menuPerformance(Request $request)
    {
        // Validation
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'category_id' => 'nullable|exists:categories,id',
            'sort_by' => 'in:revenue,quantity',
            'limit' => 'integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $endDate = Carbon::parse($request->end_date)->endOfDay();
            $sortBy = $request->input('sort_by', 'revenue');
            $limit = $request->input('limit', 10);

            $query = DB::table('order_items as oi')
                ->join('menus as m', 'oi.menu_id', '=', 'm.id')
                ->join('categories as c', 'm.category_id', '=', 'c.id')
                ->join('orders as o', 'oi.order_id', '=', 'o.id')
                ->where('o.payment_status', 'Paid')
                ->whereBetween('o.created_at', [$startDate, $endDate]);

            if ($request->has('category_id')) {
                $query->where('m.category_id', $request->category_id);
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

            return response()->json([
                'success' => true,
                'data' => [
                    'period' => [
                        'start_date' => $startDate->format('Y-m-d'),
                        'end_date' => $endDate->format('Y-m-d')
                    ],
                    'menus' => $menus->map(function($menu) {
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
                    })
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate menu performance report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get peak hours analysis
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function peakHours(Request $request)
    {
        // Validation
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $endDate = Carbon::parse($request->end_date)->endOfDay();

            $hourlyData = DB::table('orders')
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

            return response()->json([
                'success' => true,
                'data' => [
                    'period' => [
                        'start_date' => $startDate->format('Y-m-d'),
                        'end_date' => $endDate->format('Y-m-d')
                    ],
                    'hourly_data' => $hourlyData->map(function($item) {
                        return [
                            'hour' => (int) $item->hour,
                            'orders_count' => (int) $item->orders_count,
                            'revenue' => (float) $item->revenue,
                            'avg_order_value' => (float) $item->avg_order_value
                        ];
                    })
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate peak hours report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export report to CSV
     * 
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function export(Request $request)
    {
        // Validation
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:overview,revenue,menu-performance,peak-hours',
            'format' => 'required|in:csv,xlsx',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $type = $request->type;
            $format = $request->format;

            // Get data based on type
            switch ($type) {
                case 'overview':
                    $data = $this->getOverviewExportData($request);
                    $filename = "overview_report_{$request->start_date}_to_{$request->end_date}.{$format}";
                    break;
                case 'revenue':
                    $data = $this->getRevenueExportData($request);
                    $filename = "revenue_report_{$request->start_date}_to_{$request->end_date}.{$format}";
                    break;
                case 'menu-performance':
                    $data = $this->getMenuPerformanceExportData($request);
                    $filename = "menu_performance_{$request->start_date}_to_{$request->end_date}.{$format}";
                    break;
                case 'peak-hours':
                    $data = $this->getPeakHoursExportData($request);
                    $filename = "peak_hours_{$request->start_date}_to_{$request->end_date}.{$format}";
                    break;
                default:
                    throw new \Exception('Invalid report type');
            }

            // Generate CSV
            if ($format === 'csv') {
                $csv = $this->generateCSV($data);
                
                return response($csv)
                    ->header('Content-Type', 'text/csv')
                    ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
            }

            // For XLSX, you would need PhpSpreadsheet library
            // This is a placeholder - implement actual XLSX generation
            return response()->json([
                'success' => false,
                'message' => 'XLSX export not yet implemented'
            ], 501);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ==================== HELPER METHODS ====================

    private function getSummary($startDate, $endDate)
    {
        $data = DB::table('orders')
            ->where('payment_status', 'Paid')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                COALESCE(SUM(total_amount), 0) as total_revenue,
                COUNT(*) as total_orders,
                COUNT(DISTINCT customer_id) as total_customers,
                COALESCE(AVG(total_amount), 0) as avg_order_value
            ')
            ->first();

        // Calculate growth rate vs previous period
        $previousStart = $startDate->copy()->subDays($startDate->diffInDays($endDate) + 1);
        $previousEnd = $startDate->copy()->subDay();
        $previousRevenue = $this->getPreviousPeriodRevenue($previousStart, $previousEnd);

        $growthRate = 0;
        if ($previousRevenue > 0) {
            $growthRate = (($data->total_revenue - $previousRevenue) / $previousRevenue) * 100;
        }

        return [
            'total_revenue' => (float) $data->total_revenue,
            'total_orders' => (int) $data->total_orders,
            'total_customers' => (int) $data->total_customers,
            'avg_order_value' => (float) $data->avg_order_value,
            'growth_rate' => round($growthRate, 2)
        ];
    }

    private function getDailyBreakdown($startDate, $endDate)
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

    private function getCategoryBreakdownByDate($startDate, $endDate)
    {
        $totalRevenue = DB::table('orders')
            ->where('payment_status', 'Paid')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('total_amount');

        // ✅ Fixed: Gunakan formula yang sama dengan PostgreSQL query
        $data = DB::table('orders as o')
            ->join('order_items as oi', 'o.id', '=', 'oi.order_id')
            ->join('menus as m', 'oi.menu_id', '=', 'm.id')
            ->join('categories as c', 'm.category_id', '=', 'c.id')
            ->where('o.payment_status', 'Paid')
            ->where('o.subtotal', '>', 0) // ✅ Tambahkan filter ini
            ->whereBetween('o.created_at', [$startDate, $endDate])
            ->selectRaw('
                c.id as category_id,
                c.name as category_name,
                COALESCE(SUM(
                    oi.subtotal 
                    + (oi.subtotal / o.subtotal) * o.service_charge_amount
                    + (oi.subtotal / o.subtotal) * o.tax_amount
                ), 0) as revenue,
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
                'orders' => (int) $item->orders,
                'percentage' => $percentage
            ];
        })->toArray();
    }

    private function getTopMenusByDate($startDate, $endDate, $limit = 10)
    {
        $menus = DB::table('order_items as oi')
            ->join('menus as m', 'oi.menu_id', '=', 'm.id')
            ->join('categories as c', 'm.category_id', '=', 'c.id')
            ->join('orders as o', 'oi.order_id', '=', 'o.id')
            ->where('o.payment_status', 'Paid')
            ->whereBetween('o.created_at', [$startDate, $endDate])
            ->selectRaw('
                m.id as menu_id,
                m.name,
                c.name as category,
                SUM(oi.quantity) as quantity_sold,
                SUM(oi.subtotal) as revenue
            ')
            ->groupBy('m.id', 'm.name', 'c.name')
            ->orderByDesc('quantity_sold')
            ->limit($limit)
            ->get();

        return $menus->map(function($menu) {
            return [
                'menu_id' => $menu->menu_id,
                'name' => $menu->name,
                'category' => $menu->category,
                'quantity_sold' => (int) $menu->quantity_sold,
                'revenue' => (float) $menu->revenue
            ];
        })->toArray();
    }

    private function getPaymentMethodBreakdown($startDate, $endDate)
    {
        $data = DB::table('orders')
            ->where('payment_status', 'Paid')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw("
                CASE 
                    WHEN payment_reference LIKE 'CASH-%' THEN 'Cash'
                    WHEN payment_reference LIKE 'SIM_%' THEN 'Online'
                    WHEN payment_reference IS NULL THEN 'Cash'
                    ELSE 'Online'
                END as method,
                COUNT(*) as count,
                COALESCE(SUM(total_amount), 0) as amount
            ")
            ->groupByRaw("
                CASE 
                    WHEN payment_reference LIKE 'CASH-%' THEN 'Cash'
                    WHEN payment_reference LIKE 'SIM_%' THEN 'Online'
                    WHEN payment_reference IS NULL THEN 'Cash'
                    ELSE 'Online'
                END
            ")
            ->get();

        $total = $data->sum('amount');

        return $data->map(function($item) use ($total) {
            $percentage = $total > 0 
                ? round(($item->amount / $total) * 100, 2)
                : 0;

            return [
                'method' => $item->method ?? 'Cash',
                'count' => (int) $item->count,
                'amount' => (float) $item->amount,
                'percentage' => $percentage
            ];
        })->toArray();
    }

    private function getPreviousPeriodRevenue($startDate, $endDate, $categoryId = null)
    {
        if ($categoryId) {
            return DB::table('orders as o')
                ->join(DB::raw("(
                    SELECT 
                        oi.order_id,
                        SUM(oi.subtotal) as category_subtotal
                    FROM order_items oi
                    JOIN menus m ON oi.menu_id = m.id
                    WHERE m.category_id = {$categoryId}
                    GROUP BY oi.order_id
                ) as cat_items"), 'o.id', '=', 'cat_items.order_id')
                ->where('o.payment_status', 'Paid')
                ->whereBetween('o.created_at', [$startDate, $endDate])
                ->selectRaw('
                    COALESCE(SUM(
                        cat_items.category_subtotal * (o.total_amount / o.subtotal)
                    ), 0) as total
                ')
                ->value('total') ?? 0;
        }
        
        return DB::table('orders')
            ->where('payment_status', 'Paid')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('total_amount') ?? 0;
    }


    private function generateCSV($data)
    {
        $output = fopen('php://temp', 'r+');
        
        if (!empty($data)) {
            // Write header
            fputcsv($output, array_keys($data[0]));
            
            // Write data
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }

    private function getOverviewExportData($request)
    {
        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();
        
        return $this->getDailyBreakdown($startDate, $endDate);
    }

    private function getRevenueExportData($request)
    {
        // Similar to getOverviewExportData but with more revenue details
        return $this->getOverviewExportData($request);
    }

    private function getMenuPerformanceExportData($request)
    {
        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();
        
        return $this->getTopMenusByDate($startDate, $endDate, 100);
    }

    private function getPeakHoursExportData($request)
    {
        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();

        $data = DB::table('orders')
            ->where('payment_status', 'Paid')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                EXTRACT(HOUR FROM created_at) as hour,
                COUNT(*) as orders_count,
                COALESCE(SUM(total_amount), 0) as revenue
            ')
            ->groupBy('hour')
            ->orderBy('hour', 'ASC')
            ->get();

        return $data->map(function($item) {
            return [
                'hour' => (int) $item->hour,
                'orders_count' => (int) $item->orders_count,
                'revenue' => (float) $item->revenue
            ];
        })->toArray();
    }

    /**
     * Get period label for display
     */
    private function getPeriodLabel($startDate, $endDate, $viewType)
    {
        if ($viewType === '5y') {
            return $startDate->format('Y') . ' - ' . $endDate->format('Y');
        }
        return $startDate->format('M Y') . ' - ' . $endDate->format('M Y');
    }
    
    /**
     * Get aggregated revenue by period
     * FIXED: No more $year parameter, use NOW() as base
     */
    public function revenueAggregated(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'view_type' => 'required|in:3m,6m,1y,5y',
            'category_id' => 'nullable|exists:categories,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $viewType = $request->view_type;
            $categoryId = $request->category_id;

            // 🔥 Always use NOW as base (timezone already set in config)
            $now = now();
            $ranges = $this->calculateDateRanges($now, $viewType);
            
            // Get current period data
            $currentData = $this->getAggregatedData(
                $ranges['current_start'],
                $ranges['current_end'],
                $categoryId,
                $viewType
            );
            
            // Get previous period data for comparison
            $previousData = $this->getAggregatedData(
                $ranges['previous_start'],
                $ranges['previous_end'],
                $categoryId,
                $viewType
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'view_type' => $viewType,
                    'current_period' => [
                        'start' => $ranges['current_start']->format('Y-m-d'),
                        'end' => $ranges['current_end']->format('Y-m-d'),
                        'label' => $this->getPeriodLabel($ranges['current_start'], $ranges['current_end'], $viewType)
                    ],
                    'previous_period' => [
                        'start' => $ranges['previous_start']->format('Y-m-d'),
                        'end' => $ranges['previous_end']->format('Y-m-d'),
                        'label' => $this->getPeriodLabel($ranges['previous_start'], $ranges['previous_end'], $viewType)
                    ],
                    'current_data' => $currentData,
                    'previous_data' => $previousData,
                    'comparison' => $this->calculateComparison($currentData, $previousData)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate aggregated report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 🔥 FIXED: Calculate from NOW, not from year parameter
     */
    private function calculateDateRanges($now, $viewType)
    {
        switch ($viewType) {
            case '3m': // Last 3 months from now
                $currentEnd = $now->copy()->endOfMonth();
                $currentStart = $now->copy()->subMonths(2)->startOfMonth();
                $previousEnd = $currentStart->copy()->subDay()->endOfMonth();
                $previousStart = $previousEnd->copy()->subMonths(2)->startOfMonth();
                break;
                
            case '6m': // Last 6 months from now
                $currentEnd = $now->copy()->endOfMonth();
                $currentStart = $now->copy()->subMonths(5)->startOfMonth();
                $previousEnd = $currentStart->copy()->subDay()->endOfMonth();
                $previousStart = $previousEnd->copy()->subMonths(5)->startOfMonth();
                break;
                
            case '1y': // Last 12 months from now
                $currentEnd = $now->copy()->endOfMonth();
                $currentStart = $now->copy()->subMonths(11)->startOfMonth();
                $previousEnd = $currentStart->copy()->subDay()->endOfMonth();
                $previousStart = $previousEnd->copy()->subMonths(11)->startOfMonth();
                break;

            case '5y': // Last 5 full years
                $currentEnd = $now->copy()->endOfYear();
                $currentStart = $now->copy()->subYears(4)->startOfYear();
                $previousEnd = $currentStart->copy()->subDay()->endOfYear();
                $previousStart = $previousEnd->copy()->subYears(4)->startOfYear();
                break;
        }
        
        return [
            'current_start' => $currentStart,
            'current_end' => $currentEnd,
            'previous_start' => $previousStart,
            'previous_end' => $previousEnd
        ];
    }

    /**
     * 🔥 Get aggregated data with proper grouping
     * FIXED: Tambahkan filter o.subtotal > 0 dan handle NULL
     */
    private function getAggregatedData($startDate, $endDate, $categoryId = null, $viewType = '1y')
    {
        if ($categoryId) {
            // ✅ Per category dengan proportional distribution
            if ($viewType === '5y') {
                $data = DB::table('orders as o')
                    ->join(DB::raw("(
                        SELECT 
                            oi.order_id,
                            SUM(oi.subtotal) as category_subtotal
                        FROM order_items oi
                        JOIN menus m ON oi.menu_id = m.id
                        WHERE m.category_id = {$categoryId}
                        GROUP BY oi.order_id
                    ) as cat_items"), 'o.id', '=', 'cat_items.order_id')
                    ->where('o.payment_status', 'Paid')
                    ->where('o.subtotal', '>', 0) // ✅ CRITICAL FIX
                    ->whereBetween('o.created_at', [$startDate, $endDate])
                    ->selectRaw("
                        EXTRACT(YEAR FROM o.created_at)::INTEGER as year,
                        COALESCE(SUM(
                            cat_items.category_subtotal 
                            + (cat_items.category_subtotal / o.subtotal) * COALESCE(o.service_charge_amount, 0)
                            + (cat_items.category_subtotal / o.subtotal) * COALESCE(o.tax_amount, 0)
                        ), 0) as revenue,
                        COUNT(DISTINCT o.id) as orders_count,
                        COUNT(DISTINCT o.customer_id) as customers_count,
                        COALESCE(AVG(
                            cat_items.category_subtotal 
                            + (cat_items.category_subtotal / o.subtotal) * COALESCE(o.service_charge_amount, 0)
                            + (cat_items.category_subtotal / o.subtotal) * COALESCE(o.tax_amount, 0)
                        ), 0) as avg_order_value
                    ")
                    ->groupByRaw('EXTRACT(YEAR FROM o.created_at)')
                    ->orderByRaw('EXTRACT(YEAR FROM o.created_at) ASC')
                    ->get();
                
                return $data->map(function($item) {
                    return [
                        'period' => (string) $item->year,
                        'period_label' => (string) $item->year,
                        'year' => (int) $item->year,
                        'revenue' => (float) $item->revenue,
                        'orders_count' => (int) $item->orders_count,
                        'customers_count' => (int) $item->customers_count,
                        'avg_order_value' => (float) $item->avg_order_value
                    ];
                })->toArray();
            }
            
            // Monthly view untuk per category
            $data = DB::table('orders as o')
                ->join(DB::raw("(
                    SELECT 
                        oi.order_id,
                        SUM(oi.subtotal) as category_subtotal
                    FROM order_items oi
                    JOIN menus m ON oi.menu_id = m.id
                    WHERE m.category_id = {$categoryId}
                    GROUP BY oi.order_id
                ) as cat_items"), 'o.id', '=', 'cat_items.order_id')
                ->where('o.payment_status', 'Paid')
                ->where('o.subtotal', '>', 0) // ✅ CRITICAL FIX
                ->whereBetween('o.created_at', [$startDate, $endDate])
                ->selectRaw("
                    EXTRACT(YEAR FROM o.created_at)::INTEGER as year,
                    EXTRACT(MONTH FROM o.created_at)::INTEGER as month,
                    TRIM(TO_CHAR(o.created_at, 'Mon')) as month_short,
                    COALESCE(SUM(
                        cat_items.category_subtotal 
                        + (cat_items.category_subtotal / o.subtotal) * COALESCE(o.service_charge_amount, 0)
                        + (cat_items.category_subtotal / o.subtotal) * COALESCE(o.tax_amount, 0)
                    ), 0) as revenue,
                    COUNT(DISTINCT o.id) as orders_count,
                    COUNT(DISTINCT o.customer_id) as customers_count,
                    COALESCE(AVG(
                        cat_items.category_subtotal 
                        + (cat_items.category_subtotal / o.subtotal) * COALESCE(o.service_charge_amount, 0)
                        + (cat_items.category_subtotal / o.subtotal) * COALESCE(o.tax_amount, 0)
                    ), 0) as avg_order_value
                ")
                ->groupByRaw('EXTRACT(YEAR FROM o.created_at), EXTRACT(MONTH FROM o.created_at), TO_CHAR(o.created_at, \'Mon\')')
                ->orderByRaw('EXTRACT(YEAR FROM o.created_at) ASC, EXTRACT(MONTH FROM o.created_at) ASC')
                ->get();
            
            return $data->map(function($item) {
                return [
                    'period' => trim($item->month_short) . ' ' . $item->year,
                    'period_label' => trim($item->month_short) . ' ' . $item->year,
                    'year' => (int) $item->year,
                    'month' => (int) $item->month,
                    'revenue' => (float) $item->revenue,
                    'orders_count' => (int) $item->orders_count,
                    'customers_count' => (int) $item->customers_count,
                    'avg_order_value' => (float) $item->avg_order_value
                ];
            })->toArray();
        }
        
        // ✅ All categories - use total_amount (tidak perlu filter karena sudah menggunakan total_amount)
        if ($viewType === '5y') {
            $data = DB::table('orders')
                ->where('payment_status', 'Paid')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->selectRaw("
                    EXTRACT(YEAR FROM created_at)::INTEGER as year,
                    COALESCE(SUM(total_amount), 0) as revenue,
                    COUNT(DISTINCT id) as orders_count,
                    COUNT(DISTINCT customer_id) as customers_count,
                    COALESCE(AVG(total_amount), 0) as avg_order_value
                ")
                ->groupByRaw('EXTRACT(YEAR FROM created_at)')
                ->orderByRaw('EXTRACT(YEAR FROM created_at) ASC')
                ->get();
            
            return $data->map(function($item) {
                return [
                    'period' => (string) $item->year,
                    'period_label' => (string) $item->year,
                    'year' => (int) $item->year,
                    'revenue' => (float) $item->revenue,
                    'orders_count' => (int) $item->orders_count,
                    'customers_count' => (int) $item->customers_count,
                    'avg_order_value' => (float) $item->avg_order_value
                ];
            })->toArray();
        }
        
        // Monthly view untuk all categories
        $data = DB::table('orders')
            ->where('payment_status', 'Paid')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw("
                EXTRACT(YEAR FROM created_at)::INTEGER as year,
                EXTRACT(MONTH FROM created_at)::INTEGER as month,
                TRIM(TO_CHAR(created_at, 'Mon')) as month_short,
                COALESCE(SUM(total_amount), 0) as revenue,
                COUNT(DISTINCT id) as orders_count,
                COUNT(DISTINCT customer_id) as customers_count,
                COALESCE(AVG(total_amount), 0) as avg_order_value
            ")
            ->groupByRaw('EXTRACT(YEAR FROM created_at), EXTRACT(MONTH FROM created_at), TO_CHAR(created_at, \'Mon\')')
            ->orderByRaw('EXTRACT(YEAR FROM created_at) ASC, EXTRACT(MONTH FROM created_at) ASC')
            ->get();
        
        return $data->map(function($item) {
            return [
                'period' => trim($item->month_short) . ' ' . $item->year,
                'period_label' => trim($item->month_short) . ' ' . $item->year,
                'year' => (int) $item->year,
                'month' => (int) $item->month,
                'revenue' => (float) $item->revenue,
                'orders_count' => (int) $item->orders_count,
                'customers_count' => (int) $item->customers_count,
                'avg_order_value' => (float) $item->avg_order_value
            ];
        })->toArray();
    }

    /**
     * Calculate comparison between two periods
     */
    private function calculateComparison($currentData, $previousData)
    {
        $currentTotal = array_sum(array_column($currentData, 'revenue'));
        $previousTotal = array_sum(array_column($previousData, 'revenue'));
        
        $growthRate = 0;
        if ($previousTotal > 0) {
            $growthRate = (($currentTotal - $previousTotal) / $previousTotal) * 100;
        }
        
        return [
            'current_total' => $currentTotal,
            'previous_total' => $previousTotal,
            'growth_rate' => round($growthRate, 2),
            'difference' => $currentTotal - $previousTotal
        ];
    }
}