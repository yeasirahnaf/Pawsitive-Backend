<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Extensions ───────────────────────────────────────────────────────
        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        // ─── User role ────────────────────────────────────────────────────────
        DB::statement("CREATE TYPE user_role AS ENUM ('customer', 'admin')");

        // ─── Pet attributes ───────────────────────────────────────────────────
        DB::statement("CREATE TYPE pet_gender AS ENUM ('male', 'female')");
        DB::statement("CREATE TYPE pet_size AS ENUM ('small', 'medium', 'large', 'extra_large')");
        DB::statement("CREATE TYPE pet_status AS ENUM ('available', 'reserved', 'sold')");

        // ─── Order lifecycle ──────────────────────────────────────────────────
        DB::statement("CREATE TYPE order_status AS ENUM ('pending', 'confirmed', 'out_for_delivery', 'delivered', 'cancelled')");
        DB::statement("CREATE TYPE payment_method AS ENUM ('cod')");

        // ─── Delivery lifecycle ───────────────────────────────────────────────
        DB::statement("CREATE TYPE delivery_status AS ENUM ('pending', 'dispatched', 'delivered', 'failed')");

        // ─── System settings value type ───────────────────────────────────────
        DB::statement("CREATE TYPE setting_type AS ENUM ('string', 'integer', 'boolean', 'json')");
    }

    public function down(): void
    {
        DB::statement('DROP TYPE IF EXISTS setting_type CASCADE');
        DB::statement('DROP TYPE IF EXISTS delivery_status CASCADE');
        DB::statement('DROP TYPE IF EXISTS payment_method CASCADE');
        DB::statement('DROP TYPE IF EXISTS order_status CASCADE');
        DB::statement('DROP TYPE IF EXISTS pet_status CASCADE');
        DB::statement('DROP TYPE IF EXISTS pet_size CASCADE');
        DB::statement('DROP TYPE IF EXISTS pet_gender CASCADE');
        DB::statement('DROP TYPE IF EXISTS user_role CASCADE');
    }
};


