<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('key', 100)->unique();
            $table->text('value');
            $table->text('description')->nullable();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('updated_at')->default(DB::raw('NOW()'));
        });

        // ENUM for value type
        DB::statement("ALTER TABLE system_settings ADD COLUMN type setting_type NOT NULL DEFAULT 'string'");

        // Seed default settings
        DB::table('system_settings')->insert([
            ['key' => 'cart_lock_duration_minutes',  'value' => '15',    'type' => 'integer', 'description' => 'Minutes a pet stays locked in cart before auto-release'],
            ['key' => 'max_upload_size_mb',          'value' => '5',     'type' => 'integer', 'description' => 'Maximum image upload size in megabytes'],
            ['key' => 'email_notifications_enabled', 'value' => 'true',  'type' => 'boolean', 'description' => 'Master toggle for all transactional emails'],
            ['key' => 'maintenance_mode',            'value' => 'false', 'type' => 'boolean', 'description' => 'Set to true to put the storefront in read-only mode'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
