<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delivery extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'order_id',
        'status',
        'scheduled_date',
        'dispatched_at',
        'delivered_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'dispatched_at'  => 'datetime',
            'delivered_at'   => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
