@extends('layouts.app')

@section('content')
<div class="min-h-[70vh] flex items-center justify-center py-8 px-4">
    <div class="max-w-md w-full bg-white rounded-2xl shadow-sm border border-slate-200 p-8 space-y-6">
        <div class="text-center">
            <span class="inline-flex w-12 h-12 rounded-xl bg-indigo-600 text-white items-center justify-center font-bold text-2xl shadow-sm mb-3">
                E
            </span>
            <h1 class="text-2xl font-bold text-slate-900">
                {{ $isRtl ? 'ایجوکور لاگ ان' : 'Sign In to EduCore' }}
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                {{ $tenant ? $tenant->name : ($isRtl ? 'سپر ایڈمن کنٹرول پلین' : 'Platform Control Plane') }}
            </p>
        </div>

        @if($errors->any())
            <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-lg text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.post') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-xs font-semibold uppercase text-slate-600 mb-1">
                    {{ $isRtl ? 'ای میل ایڈریس' : 'Email Address' }}
                </label>
                <input
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    placeholder="name@institute.pk"
                    class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase text-slate-600 mb-1">
                    {{ $isRtl ? 'پاس ورڈ' : 'Password' }}
                </label>
                <input
                    type="password"
                    name="password"
                    required
                    class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
            </div>

            <div class="flex items-center justify-between text-xs">
                <label class="flex items-center gap-2 text-slate-600">
                    <input type="checkbox" name="remember" class="rounded text-indigo-600">
                    <span>{{ $isRtl ? 'مجھے یاد رکھیں' : 'Remember me' }}</span>
                </label>
            </div>

            <button
                type="submit"
                class="w-full py-3 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg shadow-sm transition text-sm"
            >
                {{ $isRtl ? 'لاگ ان کریں' : 'Sign In' }}
            </button>
        </form>
    </div>
</div>
@endsection
