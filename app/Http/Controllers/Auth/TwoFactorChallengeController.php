<?php

namespace App\Http\Controllers\Auth;

use App\Core\Security\TotpService;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class TwoFactorChallengeController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('two_factor_pending_user_id')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge', [
            'tenant' => app(TenantContext::class)->getTenant(),
        ]);
    }

    public function store(Request $request, TotpService $totp): RedirectResponse
    {
        $userId = $request->session()->get('two_factor_pending_user_id');
        if (!$userId) {
            return redirect()->route('login');
        }

        $throttleKey = '2fa|'.$userId.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            abort(429, 'Too many two-factor verification attempts. Please wait and try again.');
        }

        $request->validate([
            'code' => ['nullable', 'string', 'max:20'],
            'recovery_code' => ['nullable', 'string', 'max:30'],
        ]);

        $user = User::find($userId);
        if (!$user || !$user->two_factor_secret || !$user->two_factor_confirmed_at) {
            $request->session()->forget(['two_factor_pending_user_id', 'two_factor_remember']);
            return redirect()->route('login');
        }

        $verified = false;
        $code = trim((string) $request->input('code'));
        $recoveryCode = strtoupper(trim((string) $request->input('recovery_code')));

        if ($code !== '' && $totp->verifyCode((string) $user->two_factor_secret, $code)) {
            $verified = true;
        } elseif ($recoveryCode !== '') {
            $codes = is_array($user->two_factor_recovery_codes) ? $user->two_factor_recovery_codes : [];
            $matchIndex = array_search($recoveryCode, $codes, true);
            if ($matchIndex !== false) {
                unset($codes[$matchIndex]);
                $user->update(['two_factor_recovery_codes' => array_values($codes)]);
                $verified = true;
            }
        }

        if (!$verified) {
            RateLimiter::hit($throttleKey, 60);

            return back()->withErrors([
                'code' => 'The provided two-factor authentication or recovery code is invalid.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        $remember = (bool) $request->session()->pull('two_factor_remember', false);
        $request->session()->forget('two_factor_pending_user_id');

        Auth::login($user, $remember);
        $request->session()->regenerate();

        $tenant = app(TenantContext::class)->getTenant();

        return $tenant
            ? redirect()->intended(route('home'))
            : redirect()->intended(route('super-admin.dashboard'));
    }
}
