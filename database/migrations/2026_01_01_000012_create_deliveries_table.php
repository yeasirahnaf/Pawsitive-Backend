<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->date('scheduled_date')->nullable();
            $table->timestampTz('dispatched_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // ENUM column for delivery status
        DB::statement("ALTER TABLE deliveries ADD COLUMN status delivery_status NOT NULL DEFAULT 'pending'");

        // Check dispatched before delivered
        DB::statement('ALTER TABLE deliveries ADD CONSTRAINT deliveries_dispatched_before_delivered CHECK (dispatched_at IS NULL OR delivered_at IS NULL OR dispatched_at <= delivered_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
