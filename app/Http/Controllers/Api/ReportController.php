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
        // Validation
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

            // Build query
            $query = DB::table('orders as o');

            if ($request->has('category_id')) {
                $query->join('order_items as oi', 'o.id', '=', 'oi.order_id')
                      ->join('menus as m', 'oi.menu_id', '=', 'm.id')
                      ->where('m.category_id', $request->category_id);
            }

            $query->where('o.payment_status', 'Paid')
                  ->whereBetween('o.created_at', [$startDate, $endDate]);

            // Get revenue data
            $revenueData = $query->selectRaw('
                DATE(o.created_at) as date,
                COALESCE(SUM(o.total_amount), 0) as revenue,
                COUNT(DISTINCT o.id) as orders_count,
                COUNT(DISTINCT o.customer_id) as customers_count,
                COALESCE(AVG(o.total_amount), 0) as avg_order_value
            ')
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->get();

            // Calculate growth rate
            $previousPeriod = $this->getPreviousPeriodRevenue(
                $startDate->copy()->subDays($startDate->diffInDays($endDate) + 1),
                $startDate->copy()->subDay()
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
        // Note: Assuming payment_method field exists in payment_logs or orders
        // Adjust query based on actual schema
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

    private function getPreviousPeriodRevenue($startDate, $endDate)
    {
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
}