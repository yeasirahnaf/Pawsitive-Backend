<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('order_number', 10)->unique();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('guest_contact_id')->nullable()->constrained('guest_contacts')->nullOnDelete();
            $table->foreignUuid('delivery_address_id')->constrained('addresses')->restrictOnDelete();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->text('cancellation_reason')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // ENUM columns via raw SQL
        DB::statement("ALTER TABLE orders ADD COLUMN payment_method payment_method NOT NULL DEFAULT 'cod'");
        DB::statement("ALTER TABLE orders ADD COLUMN status order_status NOT NULL DEFAULT 'pending'");

        // Generated column: total = subtotal + delivery_fee
        DB::statement('ALTER TABLE orders ADD COLUMN total NUMERIC(10,2) GENERATED ALWAYS AS (subtotal + delivery_fee) STORED');

        // CHECK constraints
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_subtotal_nonneg CHECK (subtotal >= 0)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_delivery_fee_nonneg CHECK (delivery_fee >= 0)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_guest_requires_contact CHECK (user_id IS NOT NULL OR guest_contact_id IS NOT NULL)');
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_cancelled_at_requires_status CHECK (cancelled_at IS NULL OR status = 'cancelled')");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_delivered_at_requires_status CHECK (delivered_at IS NULL OR status = 'delivered')");
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
