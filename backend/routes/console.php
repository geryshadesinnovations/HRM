<?php

use App\Domains\Subscription\Console\SweepSubscriptions;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
| Run by the `scheduler` container (php artisan schedule:run every 60s) or a
| single cron entry on a native host. See docs/03-SUBSCRIPTION.md.
*/

// Advance subscription lifecycle states (trial/active/grace/expired) by date.
Schedule::command(SweepSubscriptions::class)->hourly()->withoutOverlapping();
