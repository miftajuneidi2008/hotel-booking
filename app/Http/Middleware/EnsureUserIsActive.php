<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs out a user who was deactivated or deleted, on their very next request
 * (so it also works for people who are already signed in or ticked "remember me").
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // A missing is_active (migration not run yet) counts as active, so nobody is locked out by mistake.
        $inactive = $user !== null
            && ($user->deleted_at !== null || ($user->is_active !== null && ! (bool) $user->is_active));

        if (! $inactive) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            abort(403, 'This account is deactivated.');
        }

        return redirect()->route('login')
            ->withErrors(['email' => 'This account is deactivated. Please contact the owner.']);
    }
}
