<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->text('address_line');
            $table->string('city', 100)->nullable();
            $table->string('area', 100)->nullable();
            $table->timestampTz('created_at')->default(DB::raw('NOW()'));
            // No updated_at — addresses are immutable once created
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
