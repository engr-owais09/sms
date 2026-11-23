<?php

namespace App\Http\Controllers\Auth;

use App\Core\Audit\Services\TenantAuditService;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCKOUT_DECAY_SECONDS = 60;

    public function showLoginForm(): View
    {
        $tenant = app(TenantContext::class)->getTenant();

        return view('auth.login', [
            'tenant' => $tenant,
            'isRtl' => app()->getLocale() === 'ur',
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            event(new Lockout($request));
            $seconds = RateLimiter::availableIn($throttleKey);

            Log::warning('Authentication lockout triggered.', [
                'email_hash' => hash('sha256', Str::lower((string) $request->input('email'))),
                'ip' => $request->ip(),
                'retry_after' => $seconds,
            ]);

            abort(429, trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]));
        }

        $remember = $request->boolean('remember');

        if (!Auth::attempt($credentials, $remember)) {
            RateLimiter::hit($throttleKey, self::LOCKOUT_DECAY_SECONDS);

            return back()->withErrors([
                'email' => 'Invalid email or password.',
            ])->onlyInput('email');
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        $user = Auth::user();
        $tenant = app(TenantContext::class)->getTenant();

        if ($user->status !== 'active') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => 'Your account has been suspended or deactivated.',
            ]);
        }

        // Enforce tenant boundary unless Super Admin
        if ($tenant && !$user->isSuperAdmin() && $user->tenant_id !== $tenant->id) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => 'These credentials do not belong to this workspace.',
            ]);
        }

        if (!$tenant && !$user->isSuperAdmin()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => 'Please log in through your institute subdomain.',
            ]);
        }

        if ($user->two_factor_confirmed_at !== null && !empty($user->two_factor_secret)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $request->session()->put('two_factor_pending_user_id', $user->id);
            $request->session()->put('two_factor_remember', $remember);

            return redirect()->route('two-factor.login');
        }

        if ($tenant) {
            TenantAuditService::record('auth.login', \App\Models\User::class, (string) $user->id, ['email' => $user->email], $tenant->id, $user->id);

            setPermissionsTeamId($tenant->id);
            if ($user->hasRole('parent')) {
                return redirect()->intended(route('portal.parent'));
            }
            if ($user->hasRole('student')) {
                return redirect()->intended(route('portal.student'));
            }
        }

        return $tenant
            ? redirect()->intended(route('home'))
            : redirect()->intended(route('super-admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $tenant = app(TenantContext::class)->getTenant();
        $userId = Auth::id();
        if ($tenant && $userId) {
            TenantAuditService::record('auth.logout', \App\Models\User::class, (string) $userId, [], $tenant->id, $userId);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    protected function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower((string) $request->input('email')).'|'.$request->ip());
    }
}
