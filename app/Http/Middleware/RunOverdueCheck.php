<?php

namespace App\Http\Middleware;

use App\Support\PropertyTime;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Makes sure the overdue-guest notifications go out even when the Laravel scheduler (cron) is not running.
 *
 * When desk staff (owner, manager, receptionist) open a page, this runs `hotel:notify-overdue` at most once
 * every 30 minutes. The command itself tells the owner, manager and receptionists about each overdue guest only
 * once per day, so running it often does not create duplicates. If the scheduler does run, nothing changes.
 */
class RunOverdueCheck
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && $request->user()?->canManageBookings()) {
            try {
                if (Cache::add('hotel:overdue-check:'.PropertyTime::today(), true, now()->addMinutes(30))) {
                    Artisan::call('hotel:notify-overdue');
                }
            } catch (Throwable $e) {
                // A problem here must never stop the page from opening.
                report($e);
            }
        }

        return $next($request);
    }
}
