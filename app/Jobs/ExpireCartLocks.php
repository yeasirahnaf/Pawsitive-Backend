<?php

namespace App\Jobs;

use App\Services\CartService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpireCartLocks implements ShouldQueue
{
    use Queueable;

    public function __construct() {}

    /**
     * Release all expired cart locks and set associated pets back to 'available'.
     * Dispatched by the scheduler every minute.
     */
    public function handle(CartService $cart): void
    {
        $cart->releaseExpiredLocks();
    }
}
