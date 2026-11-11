<?php

namespace App\Core\Localization\Middleware;

use App\Core\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleAndDirection
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = app(TenantContext::class)->getTenant();
        $user = $request->user();

        // Priority: Request query param > User preference > Session > Tenant default > App default
        if ($request->has('lang') && in_array($request->query('lang'), ['en', 'ur'])) {
            $locale = $request->query('lang');
            session(['locale' => $locale]);
        } elseif ($user && !empty($user->preferred_locale)) {
            $locale = $user->preferred_locale;
        } elseif (session()->has('locale')) {
            $locale = session('locale');
        } elseif ($tenant && !empty($tenant->default_locale)) {
            $locale = $tenant->default_locale;
        } else {
            $locale = config('app.locale', 'en');
        }

        App::setLocale($locale);

        $direction = ($locale === 'ur') ? 'rtl' : 'ltr';

        View::share('currentLocale', $locale);
        View::share('currentDirection', $direction);
        View::share('isRtl', $direction === 'rtl');

        return $next($request);
    }
}
