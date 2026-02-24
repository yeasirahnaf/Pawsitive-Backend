<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PetBehaviour extends Model
{
    public $incrementing = false;
    public $timestamps   = false;

    protected $keyType = 'string';

    protected $fillable = ['pet_id', 'behaviour'];

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }
}
