<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Custom Sanctum token model — overrides tokenable_id type to UUID (string)
 * because our users table uses UUID primary keys, not auto-increment bigints.
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    /**
     * Tell Eloquent that the morph foreign key (tokenable_id) is a UUID string.
     * Without this, Sanctum tries to cast it to integer → SQLSTATE[22P02].
     */
    public function tokenable(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo('tokenable', 'tokenable_type', 'tokenable_id');
    }
}
