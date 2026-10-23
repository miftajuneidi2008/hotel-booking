<?php

use App\Support\PropertyTime;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Every morning (hotel time): tell reception and the guest about guests who stayed past their check-out date.
// Needs the Laravel scheduler to run (cron or Windows Task Scheduler calling "php artisan schedule:run" every minute).
Schedule::command('hotel:notify-overdue')
    ->dailyAt('08:00')
    ->timezone(PropertyTime::timezone())
    ->withoutOverlapping();
