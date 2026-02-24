<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone', 20)->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->timestamps();
        });

        // Add the role and lockout columns using raw SQL (ENUM type)
        DB::statement("ALTER TABLE users ADD COLUMN role user_role NOT NULL DEFAULT 'customer'");
        DB::statement('ALTER TABLE users ADD COLUMN failed_login_attempts INTEGER NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE users ADD COLUMN locked_until TIMESTAMPTZ');
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_failed_attempts_nonneg CHECK (failed_login_attempts >= 0)');

        Schema::table('users', function (Blueprint $table) {
            // personal_access_tokens table for Sanctum
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
