<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pet extends Model
{
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'species',
        'breed',
        'age_months',
        'gender',
        'size',
        'color',
        'price',
        'health_records',
        'description',
        'status',
        'latitude',
        'longitude',
        'location_name',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'price'      => 'decimal:2',
            'age_months' => 'integer',
            'latitude'   => 'float',
            'longitude'  => 'float',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function behaviours(): HasMany
    {
        return $this->hasMany(PetBehaviour::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(PetImage::class)->orderBy('sort_order');
    }

    public function thumbnail(): HasOne
    {
        return $this->hasOne(PetImage::class)->where('is_thumbnail', true);
    }

    public function cartItem(): HasOne
    {
        return $this->hasOne(CartItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    /**
     * Sync the geo_point PostGIS column whenever lat/lng are saved.
     * Called by PetService after setting latitude/longitude.
     */
    public function syncGeoPoint(): void
    {
        if ($this->latitude !== null && $this->longitude !== null) {
            \Illuminate\Support\Facades\DB::statement(
                "UPDATE pets SET geo_point = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography WHERE id = ?",
                [$this->longitude, $this->latitude, $this->id]
            );
        }
    }
}
