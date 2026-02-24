<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Tell Sanctum to use our UUID-aware PersonalAccessToken model.
        // Without this, tokenable_id is treated as bigint → UUID insert fails.
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }
}

