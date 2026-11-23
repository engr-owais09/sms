@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm space-y-4">
        <div>
            <h1 class="text-xl font-bold text-slate-900">Account Security & Password</h1>
            @if($user->must_change_password)
                <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mt-2">
                    You must change your temporary password before accessing the workspace.
                </p>
            @endif
        </div>

        @if(session('status'))
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg text-sm">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('account.password.update') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Current Password</label>
                <input type="password" name="current_password" required class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                @error('current_password') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">New Password</label>
                <input type="password" name="password" required class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                @error('password') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Confirm New Password</label>
                <input type="password" name="password_confirmation" required class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
            </div>
            <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-lg">
                Update Password
            </button>
        </form>
    </div>

    @if(!$user->must_change_password)
        <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm space-y-4">
            <div>
                <h2 class="text-base font-bold text-slate-900">Two-Factor Authentication (TOTP)</h2>
                <p class="text-xs text-slate-500 mt-1">Protect Super Admin and Institute Owner accounts with an authenticator app and recovery codes.</p>
            </div>

            @if($user->two_factor_confirmed_at)
                <div class="text-xs bg-emerald-50 border border-emerald-200 text-emerald-800 px-3 py-2 rounded-lg font-semibold">
                    ✓ Two-factor authentication is active.
                </div>
                <form method="POST" action="{{ route('account.two-factor.disable') }}" class="flex flex-col sm:flex-row gap-3">
                    @csrf
                    <input type="password" name="current_password" required placeholder="Confirm current password" class="flex-1 px-3 py-2 text-sm border border-slate-300 rounded-lg">
                    <button type="submit" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-lg">Disable 2FA</button>
                </form>
            @elseif($user->two_factor_secret)
                <div class="space-y-3 text-xs">
                    <div class="p-3 bg-slate-50 border border-slate-200 rounded-lg font-mono break-all">
                        Secret: {{ $user->two_factor_secret }}
                    </div>
                    @if(!empty($user->two_factor_recovery_codes))
                        <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg">
                            <div class="font-bold text-amber-900 mb-1">Save these one-time recovery codes:</div>
                            <div class="grid grid-cols-2 gap-1 font-mono text-amber-800">
                                @foreach($user->two_factor_recovery_codes as $rc)
                                    <span>{{ $rc }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    <form method="POST" action="{{ route('account.two-factor.confirm') }}" class="flex gap-2">
                        @csrf
                        <input type="text" name="code" placeholder="6-digit code" required class="px-3 py-2 text-sm border border-slate-300 rounded-lg font-mono">
                        <button type="submit" class="px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-lg">Confirm 2FA</button>
                    </form>
                    @error('two_factor_code') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                </div>
            @else
                <form method="POST" action="{{ route('account.two-factor.enable') }}">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg">
                        Enable Two-Factor Authentication
                    </button>
                </form>
            @endif
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm space-y-4">
            <div>
                <h2 class="text-base font-bold text-slate-900">Sign Out Other Devices</h2>
                <p class="text-xs text-slate-500 mt-1">Invalidate all other browser sessions for this account.</p>
            </div>
            <form method="POST" action="{{ route('account.logout-other-devices') }}" class="flex flex-col sm:flex-row gap-3">
                @csrf
                <input type="password" name="current_password" required placeholder="Confirm current password" class="flex-1 px-3 py-2 text-sm border border-slate-300 rounded-lg">
                <button type="submit" class="px-4 py-2 bg-slate-900 hover:bg-rose-700 text-white text-xs font-bold rounded-lg">
                    Log Out Other Devices
                </button>
            </form>
        </div>
    @endif
</div>
@endsection
