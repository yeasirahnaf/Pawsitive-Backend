<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pet_behaviours', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('pet_id')->constrained('pets')->cascadeOnDelete();
            $table->string('behaviour', 100);

            $table->unique(['pet_id', 'behaviour'], 'pet_behaviours_pet_behaviour_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pet_behaviours');
    }
};
