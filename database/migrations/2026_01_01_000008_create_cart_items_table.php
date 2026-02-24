<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('session_id')->nullable();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('pet_id')->constrained('pets')->cascadeOnDelete();
            $table->timestampTz('locked_until');
            $table->timestamps();

            // A pet can only be in one cart at a time
            $table->unique('pet_id', 'cart_items_pet_unique');
        });

        // Either session_id or user_id must be set
        DB::statement('ALTER TABLE cart_items ADD CONSTRAINT cart_items_owner_required CHECK (session_id IS NOT NULL OR user_id IS NOT NULL)');
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
