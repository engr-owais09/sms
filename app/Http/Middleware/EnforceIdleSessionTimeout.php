<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceIdleSessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return $next($request);
        }

        $timeoutMinutes = (int) config('session.idle_timeout_minutes', 30);
        if ($timeoutMinutes <= 0) {
            return $next($request);
        }

        $now = now()->timestamp;
        $lastActivity = $request->session()->get('last_activity_at');

        if (is_numeric($lastActivity) && ($now - (int) $lastActivity) > ($timeoutMinutes * 60)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Your session expired due to inactivity. Please sign in again.',
            ]);
        }

        $request->session()->put('last_activity_at', $now);

        return $next($request);
    }
}
