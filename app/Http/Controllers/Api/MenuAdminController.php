<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * MenuAdminController
 * 
 * Handles menu management (CRUD + image upload)
 * - Categories: Kitchen, Bar, Pastry (fixed)
 * - Subcategories: 17 types (fixed)
 * - Stock tracking
 * - Image upload (max 5MB)
 */
class MenuAdminController extends Controller
{
    /**
     * File upload service
     */
    private FileUploadService $fileUploadService;

    /**
     * Available subcategories (fixed list)
     */
    private $subcategories = [
        'bagel', 'bites', 'cake', 'cheese-cake', 'coffee', 'cookies', 
        'croissant', 'etc', 'frappe', 'ice-milk-coffee', 'madeleine', 
        'mains', 'mocktail', 'roll-cake', 'roti-manis', 'scones', 'seasonal'
    ];

    /**
     * Constructor
     */
    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }

    /**
     * Get all menus with filters
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $query = DB::table('menus as m')
                ->join('categories as c', 'm.category_id', '=', 'c.id')
                ->select(
                    'm.id', 'm.category_id', 'c.name as category_name',
                    'm.name', 'm.description', 'm.price', 
                    'm.stock_quantity', 'm.minimum_stock',
                    'm.image_url', 'm.is_available', 
                    'm.preparation_time', 'm.subcategory',
                    'm.display_order', 'm.created_at', 'm.updated_at'
                );

            // Filter by category
            if ($request->has('category_id')) {
                $query->where('m.category_id', $request->category_id);
            }

            // Filter by availability
            if ($request->has('is_available')) {
                $isAvailable = filter_var($request->is_available, FILTER_VALIDATE_BOOLEAN);
                $query->where('m.is_available', $isAvailable);
            }

            // Filter by subcategory
            if ($request->has('subcategory')) {
                $query->where('m.subcategory', $request->subcategory);
            }

            // Search by name
            if ($request->has('search')) {
                $search = $request->search;
                $query->where('m.name', 'ILIKE', "%{$search}%");
            }

            // Pagination
            $limit = $request->input('limit', 20);
            $page = $request->input('page', 1);
            $offset = ($page - 1) * $limit;

            // Get total count
            $total = $query->count();

            // Get paginated data
            $menus = $query->orderBy('m.display_order', 'ASC')
                ->orderBy('m.created_at', 'DESC')
                ->limit($limit)
                ->offset($offset)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'menus' => $menus,
                    'pagination' => [
                        'total' => $total,
                        'page' => (int) $page,
                        'limit' => (int) $limit,
                        'total_pages' => ceil($total / $limit)
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch menus',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single menu by ID
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $menu = DB::table('menus as m')
                ->join('categories as c', 'm.category_id', '=', 'c.id')
                ->select(
                    'm.id', 'm.category_id', 'c.name as category_name',
                    'm.name', 'm.description', 'm.price', 
                    'm.stock_quantity', 'm.minimum_stock',
                    'm.image_url', 'm.is_available', 
                    'm.preparation_time', 'm.subcategory',
                    'm.display_order', 'm.created_at', 'm.updated_at'
                )
                ->where('m.id', $id)
                ->first();

            if (!$menu) {
                return response()->json([
                    'success' => false,
                    'message' => 'Menu not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $menu
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch menu',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new menu
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Validation
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'stock_quantity' => 'integer|min:0',
            'minimum_stock' => 'integer|min:0',
            'image_url' => 'nullable|string|max:500',
            'is_available' => 'boolean',
            'preparation_time' => 'integer|min:0',
            'subcategory' => 'nullable|in:' . implode(',', $this->subcategories),
            'display_order' => 'integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $menuId = DB::table('menus')->insertGetId([
                'category_id' => $request->category_id,
                'name' => $request->name,
                'description' => $request->input('description'),
                'price' => $request->price,
                'stock_quantity' => $request->input('stock_quantity', 0),
                'minimum_stock' => $request->input('minimum_stock', 0),
                'image_url' => $request->input('image_url'),
                'is_available' => $request->input('is_available', true),
                'preparation_time' => $request->input('preparation_time', 15),
                'subcategory' => $request->input('subcategory'),
                'display_order' => $request->input('display_order', 0),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            DB::commit();

            // Fetch created menu
            $menu = DB::table('menus as m')
                ->join('categories as c', 'm.category_id', '=', 'c.id')
                ->select(
                    'm.id', 'm.category_id', 'c.name as category_name',
                    'm.name', 'm.description', 'm.price', 
                    'm.stock_quantity', 'm.minimum_stock',
                    'm.image_url', 'm.is_available', 
                    'm.preparation_time', 'm.subcategory',
                    'm.display_order', 'm.created_at', 'm.updated_at'
                )
                ->where('m.id', $menuId)
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Menu created successfully',
                'data' => $menu
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create menu',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update existing menu
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        // Check if menu exists
        $menuExists = DB::table('menus')->where('id', $id)->exists();
        if (!$menuExists) {
            return response()->json([
                'success' => false,
                'message' => 'Menu not found'
            ], 404);
        }

        // Validation
        $validator = Validator::make($request->all(), [
            'category_id' => 'exists:categories,id',
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'price' => 'numeric|min:0',
            'stock_quantity' => 'integer|min:0',
            'minimum_stock' => 'integer|min:0',
            'image_url' => 'nullable|string|max:500',
            'is_available' => 'boolean',
            'preparation_time' => 'integer|min:0',
            'subcategory' => 'nullable|in:' . implode(',', $this->subcategories),
            'display_order' => 'integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $updateData = ['updated_at' => now()];

            if ($request->has('category_id')) $updateData['category_id'] = $request->category_id;
            if ($request->has('name')) $updateData['name'] = $request->name;
            if ($request->has('description')) $updateData['description'] = $request->description;
            if ($request->has('price')) $updateData['price'] = $request->price;
            if ($request->has('stock_quantity')) $updateData['stock_quantity'] = $request->stock_quantity;
            if ($request->has('minimum_stock')) $updateData['minimum_stock'] = $request->minimum_stock;
            if ($request->has('image_url')) $updateData['image_url'] = $request->image_url;
            if ($request->has('is_available')) $updateData['is_available'] = $request->is_available;
            if ($request->has('preparation_time')) $updateData['preparation_time'] = $request->preparation_time;
            if ($request->has('subcategory')) $updateData['subcategory'] = $request->subcategory;
            if ($request->has('display_order')) $updateData['display_order'] = $request->display_order;

            DB::table('menus')->where('id', $id)->update($updateData);

            DB::commit();

            // Fetch updated menu
            $menu = DB::table('menus as m')
                ->join('categories as c', 'm.category_id', '=', 'c.id')
                ->select(
                    'm.id', 'm.category_id', 'c.name as category_name',
                    'm.name', 'm.description', 'm.price', 
                    'm.stock_quantity', 'm.minimum_stock',
                    'm.image_url', 'm.is_available', 
                    'm.preparation_time', 'm.subcategory',
                    'm.display_order', 'm.created_at', 'm.updated_at'
                )
                ->where('m.id', $id)
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Menu updated successfully',
                'data' => $menu
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update menu',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete menu
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            // Get menu to delete image
            $menu = DB::table('menus')->where('id', $id)->first();

            if (!$menu) {
                return response()->json([
                    'success' => false,
                    'message' => 'Menu not found'
                ], 404);
            }

            // Delete image file if exists
            if ($menu->image_url) {
                $this->fileUploadService->deleteFile($menu->image_url);
            }

            // Delete menu from database
            DB::table('menus')->where('id', $id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Menu deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete menu',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload menu image
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadImage(Request $request, $id)
    {
        // Check if menu exists
        $menu = DB::table('menus')->where('id', $id)->first();
        if (!$menu) {
            return response()->json([
                'success' => false,
                'message' => 'Menu not found'
            ], 404);
        }

        // Validation
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120' // 5MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Upload image using service
            $image = $request->file('image');
            $imageUrl = $this->fileUploadService->uploadMenuImage($image, $id, $menu->image_url);

            // Update database
            DB::table('menus')
                ->where('id', $id)
                ->update([
                    'image_url' => $imageUrl,
                    'updated_at' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Image uploaded successfully',
                'data' => [
                    'image_url' => $imageUrl
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload image',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get list of available subcategories
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSubcategories()
    {
        return response()->json([
            'success' => true,
            'data' => $this->subcategories
        ]);
    }
}