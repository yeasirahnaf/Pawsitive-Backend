<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'order_number',
        'user_id',
        'guest_contact_id',
        'delivery_address_id',
        'subtotal',
        'delivery_fee',
        'payment_method',
        'status',
        'cancellation_reason',
        'cancelled_at',
        'delivered_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'subtotal'     => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'cancelled_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }



    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function guestContact(): BelongsTo
    {
        return $this->belongsTo(GuestContact::class);
    }

    public function deliveryAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'delivery_address_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderByDesc('created_at');
    }

    public function delivery(): HasOne
    {
        return $this->hasOne(Delivery::class);
    }
}
