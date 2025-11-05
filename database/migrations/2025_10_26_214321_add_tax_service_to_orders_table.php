<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Add pricing breakdown columns
            $table->decimal('subtotal', 10, 2)->default(0)->after('total_amount');
            $table->decimal('service_charge_rate', 5, 4)->default(0.0700)->after('subtotal');
            $table->decimal('service_charge_amount', 10, 2)->default(0)->after('service_charge_rate');
            $table->decimal('tax_rate', 5, 4)->default(0.1000)->after('service_charge_amount');
            $table->decimal('tax_amount', 10, 2)->default(0)->after('tax_rate');
            
            // Add index for reporting
            $table->index(['subtotal', 'service_charge_amount', 'tax_amount'], 'idx_orders_breakdown');
        });

        // Update existing orders if any
        DB::statement("
            UPDATE orders 
            SET 
                subtotal = total_amount,
                service_charge_rate = 0,
                service_charge_amount = 0,
                tax_rate = 0,
                tax_amount = 0
            WHERE subtotal = 0
        ");
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_breakdown');
            $table->dropColumn([
                'subtotal',
                'service_charge_rate',
                'service_charge_amount',
                'tax_rate',
                'tax_amount'
            ]);
        });
    }
};