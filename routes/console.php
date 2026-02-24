<?php

use App\Jobs\ExpireCartLocks;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes / Scheduler
|--------------------------------------------------------------------------
*/

Schedule::job(ExpireCartLocks::class)->everyMinute()
    ->name('expire-cart-locks')
    ->withoutOverlapping();
