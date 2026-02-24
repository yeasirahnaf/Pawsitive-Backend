<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    public $incrementing = false;
    public $timestamps   = false;

    protected $keyType = 'string';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'order_id',
        'pet_id',
        'pet_name_snapshot',
        'pet_breed_snapshot',
        'pet_species_snapshot',
        'price_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'price_snapshot' => 'decimal:2',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Pet may be null if soft-deleted after purchase. */
    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class)->withTrashed();
    }
}
