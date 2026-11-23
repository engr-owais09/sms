<?php

namespace App\Http\Controllers\Auth;

use App\Core\Security\TotpService;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AccountSecurityController extends Controller
{
    public function editPassword(Request $request, TotpService $totp): View
    {
        $user = $request->user();
        $provisioningUri = null;
        if ($user->two_factor_secret && !$user->two_factor_confirmed_at) {
            $provisioningUri = $totp->getProvisioningUri(
                config('app.name', 'EduCore SaaS'),
                $user->email,
                (string) $user->two_factor_secret
            );
        }

        return view('account.security', [
            'tenant' => app(TenantContext::class)->getTenant(),
            'user' => $user,
            'provisioningUri' => $provisioningUri,
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $request->user();
        $user->update([
            'password' => Hash::make((string) $request->input('password')),
            'must_change_password' => false,
        ]);

        $request->session()->regenerate();

        return redirect()->route('account.password.edit')->with('status', 'Password updated successfully.');
    }

    public function logoutOtherDevices(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
        ]);

        Auth::logoutOtherDevices((string) $request->input('current_password'));

        return redirect()->route('account.password.edit')->with('status', 'All other active sessions have been signed out.');
    }

    public function enableTwoFactor(Request $request, TotpService $totp): RedirectResponse
    {
        $user = $request->user();
        $secret = $totp->generateSecret();
        $recoveryCodes = $totp->generateRecoveryCodes();

        $user->update([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $recoveryCodes,
            'two_factor_confirmed_at' => null,
        ]);

        return redirect()->route('account.password.edit')->with('status', 'Two-factor secret generated. Confirm with a 6-digit authenticator code.');
    }

    public function confirmTwoFactor(Request $request, TotpService $totp): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'digits:6'],
        ]);

        $user = $request->user();
        if (!$user->two_factor_secret || !$totp->verifyCode((string) $user->two_factor_secret, (string) $request->input('code'))) {
            return back()->withErrors([
                'two_factor_code' => 'Invalid authenticator code.',
            ]);
        }

        $user->update(['two_factor_confirmed_at' => now()]);

        return redirect()->route('account.password.edit')->with('status', 'Two-factor authentication is now enabled.');
    }

    public function disableTwoFactor(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
        ]);

        $request->user()->update([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        return redirect()->route('account.password.edit')->with('status', 'Two-factor authentication disabled.');
    }
}
