<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PetImage extends Model
{
    public $incrementing = false;
    public $timestamps   = false;

    protected $keyType = 'string';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'pet_id',
        'file_path',
        'file_name',
        'mime_type',
        'file_size_bytes',
        'is_thumbnail',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_thumbnail'    => 'boolean',
            'sort_order'      => 'integer',
            'file_size_bytes' => 'integer',
        ];
    }

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    /** Return the public URL for serving this image via storage link. */
    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->file_path);
    }
}
