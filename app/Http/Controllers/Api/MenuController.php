<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Menu;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function categories()
    {
        try {
            $categories = Category::where('is_active', true)
                                ->orderBy('display_order')
                                ->withCount('activeMenus')
                                ->get();

            return response()->json([
                'success' => true,
                'data' => $categories
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data kategori'
            ], 500);
        }
    }

    public function index(Request $request)
    {
        try {
            $query = Menu::with('category')
                        ->where('is_available', true)
                        ->where('stock_quantity', '>', 0); // Only show items with stock

            // Filter by category
            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            // Search by name
            if ($request->has('search')) {
                $query->where('name', 'ILIKE', '%' . $request->search . '%');
            }

            $menus = $query->orderBy('display_order')
                        ->orderBy('name')
                        ->get()
                        ->map(function($menu) {
                            // Add stock status info
                            $menu->stock_status = $menu->stock_quantity <= $menu->minimum_stock ? 'low' : 'normal';
                            $menu->is_low_stock = $menu->stock_quantity <= $menu->minimum_stock;
                            return $menu;
                        });

            return response()->json([
                'success' => true,
                'data' => $menus
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data menu'
        ], 500);
    }
}

    public function show($id)
    {
        try {
            $menu = Menu::with('category')->find($id);

            if (!$menu) {
                return response()->json([
                    'success' => false,
                    'message' => 'Menu tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $menu
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data menu'
            ], 500);
        }
    }
}