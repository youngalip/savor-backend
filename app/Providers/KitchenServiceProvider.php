<?php

namespace App\Providers;

use App\Services\BarService;
use App\Services\KitchenService;
use App\Services\PastryService;
use App\Services\StationAssignmentService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class KitchenServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register Station Services
        $this->app->singleton(StationAssignmentService::class);
        $this->app->singleton(KitchenService::class);
        $this->app->singleton(BarService::class);
        $this->app->singleton(PastryService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register Kitchen Station Routes
        Route::prefix('api/staff/kitchen-station')
            ->middleware('api')
            ->group(function () {
                $this->registerKitchenRoutes();
            });

        // Register Bar Station Routes
        Route::prefix('api/staff/bar-station')
            ->middleware('api')
            ->group(function () {
                $this->registerBarRoutes();
            });

        // Register Pastry Station Routes
        Route::prefix('api/staff/pastry-station')
            ->middleware('api')
            ->group(function () {
                $this->registerPastryRoutes();
            });
    }

    /**
     * Register Kitchen Routes
     */
    private function registerKitchenRoutes(): void
    {
        Route::get('orders', function (\Illuminate\Http\Request $request) {
            $status = $request->query('status', 'pending');
            $service = app(KitchenService::class);
            
            $orders = $service->getOrders($status);
            
            return response()->json([
                'success' => true,
                'data' => $orders->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'order_id' => $item->order_id,
                        'order_number' => $item->order->order_number ?? null,
                        'table_number' => $item->order->table->table_number ?? null,
                        'order_item_id' => $item->order_item_id,
                        'menu_name' => $item->orderItem->menu->name,
                        'quantity' => $item->orderItem->quantity,
                        'special_notes' => $item->orderItem->special_notes,
                        'status' => $item->status,
                        'created_at' => $item->created_at,
                        'started_at' => $item->started_at,
                        'completed_at' => $item->completed_at,
                    ];
                }),
            ]);
        });

        Route::get('orders/{id}', function (int $id) {
            $service = app(KitchenService::class);
            $order = $service->getOrderDetail($id);
            
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found',
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $order,
            ]);
        });

        Route::post('orders/{id}/start', function (int $id) {
            $service = app(KitchenService::class);
            
            try {
                $order = $service->startOrder($id);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Order started',
                    'data' => $order,
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 400);
            }
        });

        Route::post('orders/{id}/complete', function (int $id) {
            $service = app(KitchenService::class);
            
            try {
                $order = $service->completeOrder($id);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Order completed',
                    'data' => $order,
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 400);
            }
        });

        Route::get('statistics', function () {
            $service = app(KitchenService::class);
            
            return response()->json([
                'success' => true,
                'data' => $service->getStatistics(),
            ]);
        });
    }

    /**
     * Register Bar Routes
     */
    private function registerBarRoutes(): void
    {
        Route::get('orders', function (\Illuminate\Http\Request $request) {
            $status = $request->query('status', 'pending');
            $service = app(BarService::class);
            
            $orders = $service->getOrders($status);
            
            return response()->json([
                'success' => true,
                'data' => $orders->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'order_id' => $item->order_id,
                        'order_number' => $item->order->order_number ?? null,
                        'table_number' => $item->order->table->table_number ?? null,
                        'order_item_id' => $item->order_item_id,
                        'menu_name' => $item->orderItem->menu->name,
                        'quantity' => $item->orderItem->quantity,
                        'special_notes' => $item->orderItem->special_notes,
                        'status' => $item->status,
                        'created_at' => $item->created_at,
                        'started_at' => $item->started_at,
                        'completed_at' => $item->completed_at,
                    ];
                }),
            ]);
        });

        Route::get('orders/{id}', function (int $id) {
            $service = app(BarService::class);
            $order = $service->getOrderDetail($id);
            
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found',
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $order,
            ]);
        });

        Route::post('orders/{id}/start', function (int $id) {
            $service = app(BarService::class);
            
            try {
                $order = $service->startOrder($id);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Order started',
                    'data' => $order,
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 400);
            }
        });

        Route::post('orders/{id}/complete', function (int $id) {
            $service = app(BarService::class);
            
            try {
                $order = $service->completeOrder($id);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Order completed',
                    'data' => $order,
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 400);
            }
        });

        Route::get('statistics', function () {
            $service = app(BarService::class);
            
            return response()->json([
                'success' => true,
                'data' => $service->getStatistics(),
            ]);
        });
    }

    /**
     * Register Pastry Routes
     */
    private function registerPastryRoutes(): void
    {
        Route::get('orders', function (\Illuminate\Http\Request $request) {
            $status = $request->query('status', 'pending');
            $service = app(PastryService::class);
            
            $orders = $service->getOrders($status);
            
            return response()->json([
                'success' => true,
                'data' => $orders->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'order_id' => $item->order_id,
                        'order_number' => $item->order->order_number ?? null,
                        'table_number' => $item->order->table->table_number ?? null,
                        'order_item_id' => $item->order_item_id,
                        'menu_name' => $item->orderItem->menu->name,
                        'quantity' => $item->orderItem->quantity,
                        'special_notes' => $item->orderItem->special_notes,
                        'status' => $item->status,
                        'created_at' => $item->created_at,
                        'started_at' => $item->started_at,
                        'completed_at' => $item->completed_at,
                    ];
                }),
            ]);
        });

        Route::get('orders/{id}', function (int $id) {
            $service = app(PastryService::class);
            $order = $service->getOrderDetail($id);
            
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found',
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $order,
            ]);
        });

        Route::post('orders/{id}/start', function (int $id) {
            $service = app(PastryService::class);
            
            try {
                $order = $service->startOrder($id);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Order started',
                    'data' => $order,
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 400);
            }
        });

        Route::post('orders/{id}/complete', function (int $id) {
            $service = app(PastryService::class);
            
            try {
                $order = $service->completeOrder($id);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Order completed',
                    'data' => $order,
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 400);
            }
        });

        Route::get('statistics', function () {
            $service = app(PastryService::class);
            
            return response()->json([
                'success' => true,
                'data' => $service->getStatistics(),
            ]);
        });
    }
}