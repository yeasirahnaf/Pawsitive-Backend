<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pet_images', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('pet_id')->constrained('pets')->cascadeOnDelete();
            $table->string('file_path', 500);
            $table->string('file_name');
            $table->string('mime_type', 100)->nullable();
            $table->integer('file_size_bytes')->nullable();
            $table->boolean('is_thumbnail')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestampTz('created_at')->default(DB::raw('NOW()'));
        });

        // CHECK constraints
        DB::statement('ALTER TABLE pet_images ADD CONSTRAINT pet_images_file_size_positive CHECK (file_size_bytes IS NULL OR file_size_bytes > 0)');
        DB::statement('ALTER TABLE pet_images ADD CONSTRAINT pet_images_sort_order_nonneg CHECK (sort_order >= 0)');

        // Partial unique index: exactly one thumbnail per pet
        DB::statement('CREATE UNIQUE INDEX pet_images_one_thumbnail_per_pet ON pet_images (pet_id) WHERE is_thumbnail = TRUE');
    }

    public function down(): void
    {
        Schema::dropIfExists('pet_images');
    }
};
