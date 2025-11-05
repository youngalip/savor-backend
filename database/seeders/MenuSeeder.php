<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        DB::table('menus')->insert([
            // ===============================
            // MAINS (tambahan untuk existing items) - category_id = 1
            // ===============================
            ['category_id' => 1, 'name' => 'Beef Rendang', 'description' => 'Slow-cooked beef in rich coconut curry sauce', 'price' => 38000.00, 'stock_quantity' => 20, 'minimum_stock' => 5, 'is_available' => true, 'preparation_time' => 8, 'display_order' => 5, 'created_at' => $now, 'updated_at' => $now],
            ['category_id' => 1, 'name' => 'Chicken Satay', 'description' => 'Grilled chicken skewers with peanut sauce', 'price' => 32000.00, 'stock_quantity' => 25, 'minimum_stock' => 5, 'is_available' => true, 'preparation_time' => 15, 'display_order' => 6, 'created_at' => $now, 'updated_at' => $now],
            ['category_id' => 1, 'name' => 'Fish & Chips', 'description' => 'Crispy battered fish with golden fries', 'price' => 35000.00, 'stock_quantity' => 18, 'minimum_stock' => 3, 'is_available' => true, 'preparation_time' => 12, 'display_order' => 7, 'created_at' => $now, 'updated_at' => $now],

            // Bites (snacks dan appetizers)
            ['category_id' => 1, 'name' => 'Crispy Tofu Bites', 'description' => 'Golden fried tofu with sweet chili sauce', 'price' => 15000.00, 'stock_quantity' => 30, 'minimum_stock' => 8, 'is_available' => true, 'preparation_time' => 8, 'display_order' => 8, 'created_at' => $now, 'updated_at' => $now],
            ['category_id' => 1, 'name' => 'Spring Rolls', 'description' => 'Fresh vegetables wrapped in crispy pastry', 'price' => 18000.00, 'stock_quantity' => 25, 'minimum_stock' => 5, 'is_available' => true, 'preparation_time' => 10, 'display_order' => 9, 'created_at' => $now, 'updated_at' => $now],
            ['category_id' => 1, 'name' => 'Chicken Wings', 'description' => 'Spicy glazed chicken wings with herbs', 'price' => 22000.00, 'stock_quantity' => 20, 'minimum_stock' => 5, 'is_available' => true, 'preparation_time' => 12, 'display_order' => 10, 'created_at' => $now, 'updated_at' => $now],

            // ===================================
            // BAR CATEGORY (ID: 2) - DRINKS
            // ===================================

            // Coffee
            ['category_id' => 2, 'name' => 'Espresso Shot', 'description' => 'Rich double shot espresso', 'price' => 15000.00, 'stock_quantity' => 100, 'minimum_stock' => 20, 'is_available' => true, 'preparation_time' => 3, 'display_order' => 6, 'created_at' => $now, 'updated_at' => $now],
            ['category_id' => 2, 'name' => 'Americano', 'description' => 'Espresso with hot water', 'price' => 18000.00, 'stock_quantity' => 100, 'minimum_stock' => 20, 'is_available' => true, 'preparation_time' => 5, 'display_order' => 7, 'created_at' => $now, 'updated_at' => $now],
            ['category_id' => 2, 'name' => 'Cappuccino', 'description' => 'Espresso with steamed milk and foam', 'price' => 22000.00, 'stock_quantity' => 80, 'minimum_stock' => 15, 'is_available' => true, 'preparation_time' => 8, 'display_order' => 8, 'created_at' => $now, 'updated_at' => $now],

            // Ice Milk Coffee
            ['category_id' => 2, 'name' => 'Iced Latte', 'description' => 'Cold espresso with chilled milk', 'price' => 25000.00, 'stock_quantity' => 80, 'minimum_stock' => 15, 'is_available' => true, 'preparation_time' => 5, 'display_order' => 9, 'created_at' => $now, 'updated_at' => $now],
            ['category_id' => 2, 'name' => 'Cold Brew Milk', 'description' => 'Cold brew coffee with fresh milk', 'price' => 28000.00, 'stock_quantity' => 60, 'minimum_stock' => 10, 'is_available' => true, 'preparation_time' => 3, 'display_order' => 10, 'created_at' => $now, 'updated_at' => $now],
            ['category_id' => 2, 'name' => 'Vanilla Ice Coffee', 'description' => 'Iced coffee with vanilla syrup and milk', 'price' => 26000.00, 'stock_quantity' => 70, 'minimum_stock' => 12, 'is_available' => true, 'preparation_time' => 6, 'display_order' => 11, 'created_at' => $now, 'updated_at' => $now],

            // Mocktail
            ['category_id' => 2, 'name' => 'Virgin Mojito', 'description' => 'Fresh mint, lime, and soda water', 'price' => 20000.00, 'stock_quantity' => 50, 'minimum_stock' => 10, 'is_available' => true, 'preparation_time' => 8, 'display_order' => 12, 'created_at' => $now, 'updated_at' => $now],
            ['category_id' => 2, 'name' => 'Tropical Punch', 'description' => 'Mixed fruit juices with coconut water', 'price' => 22000.00, 'stock_quantity' => 40, 'minimum_stock' => 8, 'is_available' => true, 'preparation_time' => 10, 'display_order' => 13, 'created_at' => $now, 'updated_at' => $now],
            ['category_id' => 2, 'name' => 'Berry Fizz', 'description' => 'Mixed berries with sparkling water', 'price' => 24000.00, 'stock_quantity' => 35, 'minimum_stock' => 8, 'is_available' => true, 'preparation_time' => 7, 'display_order' => 14, 'created_at' => $now, 'updated_at' => $now],

            // Frappe
            ['category_id' => 2, 'name' => 'Caramel Frappe', 'description' => 'Blended coffee with caramel and whipped cream', 'price' => 32000.00, 'stock_quantity' => 50, 'minimum_stock' => 10, 'is_available' => true, 'preparation_time' => 8, 'display_order' => 15, 'created_at' => $now, 'updated_at' => $now],
            ['category_id' => 2, 'name' => 'Chocolate Frappe', 'description' => 'Rich chocolate blended with ice and cream', 'price' => 30000.00, 'stock_quantity' => 45, 'minimum_stock' => 8, 'is_available' => true, 'preparation_time' => 8, 'display_order' => 16, 'created_at' => $now, 'updated_at' => $now],
            ['category_id' => 2, 'name' => 'Vanilla Frappe', 'description' => 'Smooth vanilla frappe with coffee base', 'price' => 28000.00, 'stock_quantity' => 50, 'minimum_stock' => 10, 'is_available' => true, 'preparation_time' => 8, 'display_order' => 17, 'created_at' => $now, 'updated_at' => $now],

            // Seasonal
            ['category_id' => 2, 'name' => 'Pumpkin Spice Latte', 'description' => 'Seasonal spiced latte with pumpkin flavor', 'price' => 35000.00, 'stock_quantity' => 30, 'minimum_stock' => 5, 'is_available' => true, 'preparation_time' => 10, 'display_order' => 18, 'created_at' => $now, 'updated_at' => $now],
            ['category_id' => 2, 'name' => 'Iced Matcha Latte', 'description' => 'Premium matcha with cold milk', 'price' => 32000.00, 'stock_quantity' => 40, 'minimum_stock' => 8, 'is_available' => true, 'preparation_time' => 6, 'display_order' => 19, 'created_at' => $now, 'updated_at' => $now],
            ['category_id' => 2, 'name' => 'Hibiscus Iced Tea', 'description' => 'Refreshing floral tea with tropical fruits', 'price' => 25000.00, 'stock_quantity' => 50, 'minimum_stock' => 10, 'is_available' => true, 'preparation_time' => 5, 'display_order' => 20, 'created_at' => $now, 'updated_at' => $now],

            // ETC (existing items + new ones)
            ['category_id' => 2, 'name' => 'Hot Chocolate', 'description' => 'Rich cocoa with marshmallows', 'price' => 20000.00, 'stock_quantity' => 60, 'minimum_stock' => 12, 'is_available' => true, 'preparation_time' => 8, 'display_order' => 21, 'created_at' => $now, 'updated_at' => $now],
            ['category_id' => 2, 'name' => 'Fresh Lemonade', 'description' => 'Freshly squeezed lemon with mint', 'price' => 16000.00, 'stock_quantity' => 70, 'minimum_stock' => 15, 'is_available' => true, 'preparation_time' => 5, 'display_order' => 22, 'created_at' => $now, 'updated_at' => $now],
            ['category_id' => 2, 'name' => 'Sparkling Water', 'description' => 'Premium sparkling mineral water', 'price' => 12000.00, 'stock_quantity' => 100, 'minimum_stock' => 20, 'is_available' => true, 'preparation_time' => 2, 'display_order' => 23, 'created_at' => $now, 'updated_at' => $now],

            // ===================================
            // PASTRY CATEGORY (ID: 3) - SWEETS
            // ===================================

            // Cake
            ['category_id' => 3, 'name' => 'Chocolate Fudge Cake', 'description' => 'Rich chocolate cake with fudge frosting', 'price' => 32000.00, 'stock_quantity' => 12, 'minimum_stock' => 3, 'is_available' => true, 'preparation_time' => 5, 'display_order' => 5, 'created_at' => $now, 'updated_at' => $now],
            ['category_id' => 3, 'name' => 'Red Velvet Cake', 'description' => 'Classic red velvet with cream cheese frosting', 'price' => 35000.00, 'stock_quantity' => 10, 'minimum_stock' => 2, 'is_available' => true, 'preparation_time' => 5, 'display_order' => 6, 'created_at' => $now, 'updated_at' => $now],
            ['category_id' => 3, 'name' => 'Lemon Drizzle Cake', 'description' => 'Moist lemon cake with citrus glaze', 'price' => 28000.00, 'stock_quantity' => 15, 'minimum_stock' => 3, 'is_available' => true, 'preparation_time' => 5, 'display_order' => 7, 'created_at' => $now, 'updated_at' => $now],

            // Cookies
            ['category_id' => 3, 'name' => 'Chocolate Chip Cookies', 'description' => 'Classic cookies with premium chocolate chips', 'price' => 18000.00, 'stock_quantity' => 40, 'minimum_stock' => 10, 'is_available' => true, 'preparation_time' => 2, 'display_order' => 8, 'created_at' => $now, 'updated_at' => $now],
            ['category_id' => 3, 'name' => 'Oatmeal Raisin Cookies', 'description' => 'Chewy oats with sweet raisins', 'price' => 16000.00, 'stock_quantity' => 35, 'minimum_stock' => 8, 'is_available' => true, 'preparation_time' => 2, 'display_order' => 9, 'created_at' => $now, 'updated_at' => $now],
            ['category_id' => 3, 'name' => 'Double Chocolate Cookies', 'description' => 'Rich cocoa cookies with chocolate chunks', 'price' => 20000.00, 'stock_quantity' => 30, 'minimum_stock' => 8, 'is_available' => true, 'preparation_time' => 2, 'display_order' => 10, 'created_at' => $now, 'updated_at' => $now],

            // Roll Cake
            ['category_id' => 3, 'name' => 'Strawberry Roll Cake', 'description' => 'Light sponge cake rolled with strawberry cream', 'price' => 28000.00, 'stock_quantity' => 12, 'minimum_stock' => 3, 'is_available' => true, 'preparation_time' => 3, 'display_order' => 11, 'created_at' => $now, 'updated_at' => $now],
            ['category_id' => 3, 'name' => 'Chocolate Roll Cake', 'description' => 'Chocolate sponge with rich chocolate cream', 'price' => 30000.00, 'stock_quantity' => 10, 'minimum_stock' => 3, 'is_available' => true, 'preparation_time' => 3, 'display_order' => 12, 'created_at' => $now, 'updated_at' => $now],
            ['category_id' => 3, 'name' => 'Matcha Roll Cake', 'description' => 'Green tea sponge with sweet red bean cream', 'price' => 32000.00, 'stock_quantity' => 8, 'minimum_stock' => 2, 'is_available' => true, 'preparation_time' => 3, 'display_order' => 13, 'created_at' => $now, 'updated_at' => $now],

            // Cheese Cake (existing Cheesecake Strawberry + new ones)
            ['category_id' => 3, 'name' => 'New York Cheesecake', 'description' => 'Classic creamy cheesecake with graham crust', 'price' => 38000.00, 'stock_quantity' => 8, 'minimum_stock' => 2, 'is_available' => true, 'preparation_time' => 3, 'display_order' => 14, 'created_at' => $now, 'updated_at' => $now],
            ['category_id' => 3, 'name' => 'Blueberry Cheesecake', 'description' => 'Rich cheesecake topped with fresh blueberries', 'price' => 40000.00, 'stock_quantity' => 6, 'minimum_stock' => 2, 'is_available' => true, 'preparation_time' => 3, 'display_order' => 15, 'created_at' => $now, 'updated_at' => $now],
            ['category_id' => 3, 'name' => 'Oreo Cheesecake', 'description' => 'Cheesecake with crushed Oreo cookies', 'price' => 36000.00, 'stock_quantity' => 10, 'minimum_stock' => 2, 'is_available' => true, 'preparation_time' => 3, 'display_order' => 16, 'created_at' => $now, 'updated_at' => $now],

            // ===================================
            // PASTRY CATEGORY (ID: 3) - BREADS
            // ===================================

            // Bagel
            ['category_id' => 3, 'name' => 'Everything Bagel', 'description' => 'Classic bagel with sesame, poppy seeds, and herbs', 'price' => 16000.00, 'stock_quantity' => 25, 'minimum_stock' => 5, 'is_available' => true, 'preparation_time' => 15, 'display_order' => 17, 'created_at' => $now, 'updated_at' => $now],
            ['category_id' => 3, 'name' => 'Cream Cheese Bagel', 'description' => 'Fresh bagel served with cream cheese spread', 'price' => 18000.00, 'stock_quantity' => 20, 'minimum_stock' => 5, 'is_available' => true, 'preparation_time' => 3, 'display_order' => 18, 'created_at' => $now, 'updated_at' => $now],
            ['category_id' => 3, 'name' => 'Smoked Salmon Bagel', 'description' => 'Bagel with smoked salmon, capers, and dill', 'price' => 45000.00, 'stock_quantity' => 12, 'minimum_stock' => 3, 'is_available' => true, 'preparation_time' => 8, 'display_order' => 19, 'created_at' => $now, 'updated_at' => $now],

            // Madeleine
            ['category_id' => 3, 'name' => 'Classic Madeleine', 'description' => 'Traditional French shell-shaped sponge cakes', 'price' => 22000.00, 'stock_quantity' => 30, 'minimum_stock' => 8, 'is_available' => true, 'preparation_time' => 5, 'display_order' => 20, 'created_at' => $now, 'updated_at' => $now],
            ['category_id' => 3, 'name' => 'Lemon Madeleine', 'description' => 'Delicate madeleines with fresh lemon zest', 'price' => 24000.00, 'stock_quantity' => 25, 'minimum_stock' => 6, 'is_available' => true, 'preparation_time' => 5, 'display_order' => 21, 'created_at' => $now, 'updated_at' => $now],
            ['category_id' => 3, 'name' => 'Chocolate Madeleine', 'description' => 'Rich chocolate madeleines with cocoa glaze', 'price' => 26000.00, 'stock_quantity' => 20, 'minimum_stock' => 5, 'is_available' => true, 'preparation_time' => 5, 'display_order' => 22, 'created_at' => $now, 'updated_at' => $now],

            // Scones
            ['category_id' => 3, 'name' => 'English Scones', 'description' => 'Traditional scones served with jam and cream', 'price' => 20000.00, 'stock_quantity' => 18, 'minimum_stock' => 5, 'is_available' => true, 'preparation_time' => 12, 'display_order' => 23, 'created_at' => $now, 'updated_at' => $now],
            ['category_id' => 3, 'name' => 'Blueberry Scones', 'description' => 'Buttery scones filled with fresh blueberries', 'price' => 22000.00, 'stock_quantity' => 15, 'minimum_stock' => 4, 'is_available' => true, 'preparation_time' => 12, 'display_order' => 24, 'created_at' => $now, 'updated_at' => $now],
            ['category_id' => 3, 'name' => 'Cranberry Orange Scones', 'description' => 'Scones with dried cranberries and orange zest', 'price' => 24000.00, 'stock_quantity' => 12, 'minimum_stock' => 3, 'is_available' => true, 'preparation_time' => 12, 'display_order' => 25, 'created_at' => $now, 'updated_at' => $now],

            // Croissant
            ['category_id' => 3, 'name' => 'Butter Croissant', 'description' => 'Classic French croissant with layers of butter', 'price' => 18000.00, 'stock_quantity' => 30, 'minimum_stock' => 8, 'is_available' => true, 'preparation_time' => 8, 'display_order' => 26, 'created_at' => $now, 'updated_at' => $now],
            ['category_id' => 3, 'name' => 'Almond Croissant', 'description' => 'Croissant filled with sweet almond paste', 'price' => 25000.00, 'stock_quantity' => 20, 'minimum_stock' => 5, 'is_available' => true, 'preparation_time' => 10, 'display_order' => 27, 'created_at' => $now, 'updated_at' => $now],
            ['category_id' => 3, 'name' => 'Pain au Chocolat', 'description' => 'Buttery croissant with dark chocolate inside', 'price' => 22000.00, 'stock_quantity' => 25, 'minimum_stock' => 6, 'is_available' => true, 'preparation_time' => 8, 'display_order' => 28, 'created_at' => $now, 'updated_at' => $now],

            // Roti Manis (including existing Donat + new sweet breads)
            ['category_id' => 3, 'name' => 'Cinnamon Roll', 'description' => 'Sweet spiral bread with cinnamon and sugar', 'price' => 20000.00, 'stock_quantity' => 20, 'minimum_stock' => 5, 'is_available' => true, 'preparation_time' => 15, 'display_order' => 29, 'created_at' => $now, 'updated_at' => $now],
            ['category_id' => 3, 'name' => 'Chocolate Muffin', 'description' => 'Soft muffin with chocolate chips', 'price' => 16000.00, 'stock_quantity' => 35, 'minimum_stock' => 8, 'is_available' => true, 'preparation_time' => 8, 'display_order' => 30, 'created_at' => $now, 'updated_at' => $now],
            ['category_id' => 3, 'name' => 'Banana Bread', 'description' => 'Moist banana bread with walnuts', 'price' => 18000.00, 'stock_quantity' => 15, 'minimum_stock' => 4, 'is_available' => true, 'preparation_time' => 10, 'display_order' => 31, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}
