<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('pet_id')->nullable()->constrained('pets')->nullOnDelete();
            // Snapshot columns — preserve pet data at time of purchase
            $table->string('pet_name_snapshot');
            $table->string('pet_breed_snapshot', 100)->nullable();
            $table->string('pet_species_snapshot', 100);
            $table->decimal('price_snapshot', 10, 2);
            $table->timestampTz('created_at')->default(DB::raw('NOW()'));
        });

        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_price_snapshot_nonneg CHECK (price_snapshot >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
