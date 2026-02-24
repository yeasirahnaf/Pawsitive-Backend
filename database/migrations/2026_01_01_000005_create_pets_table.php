<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pets', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('name');
            $table->string('species', 100);
            $table->string('breed', 100)->nullable();
            $table->integer('age_months');
            $table->string('color', 100)->nullable();
            $table->decimal('price', 10, 2);
            $table->text('health_records')->nullable();
            $table->text('description')->nullable();
            $table->double('latitude')->nullable();
            $table->double('longitude')->nullable();
            $table->string('location_name')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes('deleted_at', 0);
            $table->timestamps();
        });

        // Add ENUM columns via raw SQL
        DB::statement("ALTER TABLE pets ADD COLUMN gender pet_gender NOT NULL");
        DB::statement("ALTER TABLE pets ADD COLUMN size pet_size");
        DB::statement("ALTER TABLE pets ADD COLUMN status pet_status NOT NULL DEFAULT 'available'");

        // Add PostGIS geography column
        DB::statement("ALTER TABLE pets ADD COLUMN geo_point GEOGRAPHY(POINT, 4326)");

        // Add CHECK constraints
        DB::statement('ALTER TABLE pets ADD CONSTRAINT pets_age_months_range CHECK (age_months >= 0 AND age_months <= 300)');
        DB::statement('ALTER TABLE pets ADD CONSTRAINT pets_price_range CHECK (price >= 0 AND price <= 50000)');
        DB::statement('ALTER TABLE pets ADD CONSTRAINT pets_latitude_range CHECK (latitude IS NULL OR (latitude BETWEEN -90 AND 90))');
        DB::statement('ALTER TABLE pets ADD CONSTRAINT pets_longitude_range CHECK (longitude IS NULL OR (longitude BETWEEN -180 AND 180))');
    }

    public function down(): void
    {
        Schema::dropIfExists('pets');
    }
};
