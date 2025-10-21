<?php
// database/seeders/DatabaseSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Table;
use App\Models\Menu;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing data
        Menu::truncate();
        Category::truncate();
        Table::truncate();

        // Seed Categories
        $categories = [
            [
                'name' => 'Kitchen',
                'description' => 'Makanan dari dapur utama',
                'display_order' => 1,
                'is_active' => true
            ],
            [
                'name' => 'Bar',
                'description' => 'Minuman dan beverage',
                'display_order' => 2,
                'is_active' => true
            ],
            [
                'name' => 'Pastry',
                'description' => 'Kue dan dessert',
                'display_order' => 3,
                'is_active' => true
            ]
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }

        // Seed Tables
        $tables = [
            ['table_number' => 'A1', 'qr_code' => 'QR_A1_' . time(), 'status' => 'Free'],
            ['table_number' => 'A2', 'qr_code' => 'QR_A2_' . time(), 'status' => 'Free'],
            ['table_number' => 'A3', 'qr_code' => 'QR_A3_' . time(), 'status' => 'Free'],
            ['table_number' => 'B1', 'qr_code' => 'QR_B1_' . time(), 'status' => 'Free'],
            ['table_number' => 'B2', 'qr_code' => 'QR_B2_' . time(), 'status' => 'Free'],
            ['table_number' => 'B3', 'qr_code' => 'QR_B3_' . time(), 'status' => 'Free'],
            ['table_number' => 'VIP1', 'qr_code' => 'QR_VIP1_' . time(), 'status' => 'Free'],
            ['table_number' => 'VIP2', 'qr_code' => 'QR_VIP2_' . time(), 'status' => 'Free'],
            ['table_number' => 'OUTDOOR1', 'qr_code' => 'QR_OUT1_' . time(), 'status' => 'Free'],
            ['table_number' => 'OUTDOOR2', 'qr_code' => 'QR_OUT2_' . time(), 'status' => 'Free']
        ];

        foreach ($tables as $table) {
            Table::create($table);
        }

        // Seed Menus
        $menus = [
            // Kitchen Items
            [
                'category_id' => 1,
                'name' => 'Nasi Goreng Spesial',
                'description' => 'Nasi goreng dengan telur, ayam, dan kerupuk',
                'price' => 25000.00,
                'stock_quantity' => 50,
                'preparation_time' => 15,
                'display_order' => 1,
                'is_available' => true
            ],
            [
                'category_id' => 1,
                'name' => 'Mie Ayam',
                'description' => 'Mie ayam dengan pangsit dan bakso',
                'price' => 20000.00,
                'stock_quantity' => 30,
                'preparation_time' => 12,
                'display_order' => 2,
                'is_available' => true
            ],
            [
                'category_id' => 1,
                'name' => 'Ayam Geprek',
                'description' => 'Ayam crispy dengan sambal geprek level 5',
                'price' => 22000.00,
                'stock_quantity' => 25,
                'preparation_time' => 18,
                'display_order' => 3,
                'is_available' => true
            ],
            [
                'category_id' => 1,
                'name' => 'Gado-Gado',
                'description' => 'Sayuran segar dengan bumbu kacang',
                'price' => 18000.00,
                'stock_quantity' => 20,
                'preparation_time' => 10,
                'display_order' => 4,
                'is_available' => true
            ],

            // Bar Items
            [
                'category_id' => 2,
                'name' => 'Es Teh Manis',
                'description' => 'Teh manis dingin segar',
                'price' => 5000.00,
                'stock_quantity' => 100,
                'preparation_time' => 3,
                'display_order' => 1,
                'is_available' => true
            ],
            [
                'category_id' => 2,
                'name' => 'Es Jeruk',
                'description' => 'Jeruk peras segar dengan es',
                'price' => 8000.00,
                'stock_quantity' => 50,
                'preparation_time' => 5,
                'display_order' => 2,
                'is_available' => true
            ],
            [
                'category_id' => 2,
                'name' => 'Kopi Hitam',
                'description' => 'Kopi arabica premium',
                'price' => 12000.00,
                'stock_quantity' => 80,
                'preparation_time' => 8,
                'display_order' => 3,
                'is_available' => true
            ],
            [
                'category_id' => 2,
                'name' => 'Es Kopi Susu',
                'description' => 'Kopi dengan susu dan gula aren',
                'price' => 15000.00,
                'stock_quantity' => 60,
                'preparation_time' => 8,
                'display_order' => 4,
                'is_available' => true
            ],
            [
                'category_id' => 2,
                'name' => 'Jus Alpukat',
                'description' => 'Alpukat segar dengan susu',
                'price' => 18000.00,
                'stock_quantity' => 30,
                'preparation_time' => 8,
                'display_order' => 5,
                'is_available' => true
            ],

            // Pastry Items
            [
                'category_id' => 3,
                'name' => 'Brownies Coklat',
                'description' => 'Brownies coklat dengan topping kacang',
                'price' => 15000.00,
                'stock_quantity' => 20,
                'preparation_time' => 5,
                'display_order' => 1,
                'is_available' => true
            ],
            [
                'category_id' => 3,
                'name' => 'Cheesecake Strawberry',
                'description' => 'Cheesecake dengan topping strawberry',
                'price' => 25000.00,
                'stock_quantity' => 15,
                'preparation_time' => 3,
                'display_order' => 2,
                'is_available' => true
            ],
            [
                'category_id' => 3,
                'name' => 'Donat Glaze',
                'description' => 'Donat dengan glaze manis',
                'price' => 8000.00,
                'stock_quantity' => 30,
                'preparation_time' => 2,
                'display_order' => 3,
                'is_available' => true
            ],
            [
                'category_id' => 3,
                'name' => 'Es Cream Vanilla',
                'description' => 'Es krim vanilla premium 2 scoop',
                'price' => 12000.00,
                'stock_quantity' => 25,
                'preparation_time' => 2,
                'display_order' => 4,
                'is_available' => true
            ]
        ];

        foreach ($menus as $menu) {
            Menu::create($menu);
        }

        $this->command->info('Database seeded successfully!');
        $this->command->info('Categories: ' . Category::count());
        $this->command->info('Tables: ' . Table::count());
        $this->command->info('Menus: ' . Menu::count());
    }
}