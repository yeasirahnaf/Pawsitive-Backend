<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ─── pets: primary storefront filter ──────────────────────────────────
        DB::statement("CREATE INDEX idx_pets_status ON pets (status) WHERE deleted_at IS NULL");

        // ─── pets: full-text trigram search on name ────────────────────────────
        DB::statement("CREATE INDEX idx_pets_name_trgm ON pets USING GIN (name gin_trgm_ops) WHERE deleted_at IS NULL");

        // ─── pets: location radius search via PostGIS ST_DWithin ──────────────
        DB::statement("CREATE INDEX idx_pets_geo_point ON pets USING GIST (geo_point) WHERE deleted_at IS NULL");

        // ─── pet_images: FK join ───────────────────────────────────────────────
        DB::statement("CREATE INDEX idx_pet_images_pet_id ON pet_images (pet_id)");

        // ─── orders: user order history ───────────────────────────────────────
        DB::statement("CREATE INDEX idx_orders_user_id ON orders (user_id) WHERE user_id IS NOT NULL");

        // ─── order_items: FK join ──────────────────────────────────────────────
        DB::statement("CREATE INDEX idx_order_items_order_id ON order_items (order_id)");

        // ─── order_status_history: audit trail lookup ─────────────────────────
        DB::statement("CREATE INDEX idx_order_status_history_order_id ON order_status_history (order_id)");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_pets_status');
        DB::statement('DROP INDEX IF EXISTS idx_pets_name_trgm');
        DB::statement('DROP INDEX IF EXISTS idx_pets_geo_point');
        DB::statement('DROP INDEX IF EXISTS idx_pet_images_pet_id');
        DB::statement('DROP INDEX IF EXISTS idx_orders_user_id');
        DB::statement('DROP INDEX IF EXISTS idx_order_items_order_id');
        DB::statement('DROP INDEX IF EXISTS idx_order_status_history_order_id');
    }
};
