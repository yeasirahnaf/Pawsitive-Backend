<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('email')->unique();
            $table->string('name')->nullable();
            $table->string('phone', 20)->nullable();
            $table->timestampTz('created_at')->default(DB::raw('NOW()'));
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_contacts');
    }
};
