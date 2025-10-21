<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class StaffSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing staff users
        User::truncate();

        $staffUsers = [
            [
                'name' => 'Chef Ahmad',
                'email' => 'kitchen@savor.com',
                'password' => Hash::make('kitchen123'),
                'role' => 'Kitchen',
                'is_active' => true
            ],
            [
                'name' => 'Barista Sarah',
                'email' => 'bar@savor.com',
                'password' => Hash::make('bar123'),
                'role' => 'Bar',
                'is_active' => true
            ],
            [
                'name' => 'Baker Maya',
                'email' => 'pastry@savor.com',
                'password' => Hash::make('pastry123'),
                'role' => 'Pastry',
                'is_active' => true
            ],
            [
                'name' => 'Manager Budi',
                'email' => 'kasir@savor.com',
                'password' => Hash::make('kasir123'),
                'role' => 'Kasir',
                'is_active' => true
            ],
            [
                'name' => 'Owner Savor',
                'email' => 'owner@savor.com',
                'password' => Hash::make('owner123'),
                'role' => 'Owner',
                'is_active' => true
            ]
        ];

        foreach ($staffUsers as $staff) {
            User::create($staff);
        }

        $this->command->info('Kitchen staff users created successfully!');
        $this->command->info('Login credentials:');
        $this->command->info('Kitchen: kitchen@savor.com / kitchen123');
        $this->command->info('Bar: bar@savor.com / bar123');
        $this->command->info('Pastry: pastry@savor.com / pastry123');
    }
}
