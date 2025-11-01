<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Create enum type
        DB::statement("CREATE TYPE setting_type_enum AS ENUM ('string', 'integer', 'decimal', 'boolean', 'json')");

        // Create settings table
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
            $table->text('value');
            $table->string('type', 20)->default('string');
            $table->text('description')->nullable();
            $table->boolean('is_editable')->default(true);
            $table->string('category', 50)->default('general');
            $table->timestamps();

            $table->index('key');
            $table->index('category');
            $table->index('is_editable');
        });

        // Create settings_history for audit trail
        Schema::create('settings_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('setting_id')->constrained('settings')->onDelete('cascade');
            $table->string('key', 50);
            $table->text('old_value')->nullable();
            $table->text('new_value');
            $table->string('changed_by', 100)->nullable();
            $table->timestamp('changed_at')->useCurrent();

            $table->index(['setting_id', 'changed_at']);
        });

        // Insert default settings
        DB::table('settings')->insert([
            [
                'key' => 'service_charge_rate',
                'value' => '0.07',
                'type' => 'decimal',
                'description' => 'Service charge rate (7%)',
                'is_editable' => true,
                'category' => 'pricing',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'tax_rate',
                'value' => '0.10',
                'type' => 'decimal',
                'description' => 'Restaurant tax rate (10%)',
                'is_editable' => true,
                'category' => 'pricing',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'business_name',
                'value' => 'Savor Bakery Café',
                'type' => 'string',
                'description' => 'Nama bisnis',
                'is_editable' => true,
                'category' => 'business',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'session_duration_hours',
                'value' => '2',
                'type' => 'integer',
                'description' => 'Durasi session (jam)',
                'is_editable' => true,
                'category' => 'session',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);

        // Create trigger for history
        DB::unprepared("
            CREATE OR REPLACE FUNCTION log_setting_change()
            RETURNS TRIGGER AS $$
            BEGIN
                INSERT INTO settings_history (setting_id, key, old_value, new_value, changed_by, changed_at)
                VALUES (NEW.id, NEW.key, OLD.value, NEW.value, current_user, NOW());
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER settings_change_logger
                AFTER UPDATE ON settings
                FOR EACH ROW
                WHEN (OLD.value IS DISTINCT FROM NEW.value)
                EXECUTE FUNCTION log_setting_change();
        ");
    }

    public function down(): void
    {
        DB::unprepared("
            DROP TRIGGER IF EXISTS settings_change_logger ON settings;
            DROP FUNCTION IF EXISTS log_setting_change();
        ");

        Schema::dropIfExists('settings_history');
        Schema::dropIfExists('settings');
        DB::statement("DROP TYPE IF EXISTS setting_type_enum");
    }
};