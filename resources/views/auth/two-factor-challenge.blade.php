@extends('layouts.app')

@section('content')
<div class="max-w-md mx-auto bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-5">
    <div>
        <h1 class="text-xl font-bold text-slate-900">Two-Factor Verification</h1>
        <p class="text-xs text-slate-500 mt-1">Enter the 6-digit code from your authenticator app or a one-time recovery code.</p>
    </div>

    <form method="POST" action="{{ route('two-factor.login.store') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Authenticator Code</label>
            <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" placeholder="123456" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg font-mono">
            @error('code') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Or Recovery Code</label>
            <input type="text" name="recovery_code" placeholder="XXXXX-XXXXX" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg font-mono uppercase">
        </div>

        <button type="submit" class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-lg">
            Verify &amp; Continue
        </button>
    </form>
</div>
@endsection
