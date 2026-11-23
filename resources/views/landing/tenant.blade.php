@extends('layouts.app')

@section('content')
@php
    $portalBadgeKey = $tenant->type === 'hybrid'
        ? 'sms.landing.tenant.portal_badge_hybrid'
        : ($tenant->isSchool() ? 'sms.landing.tenant.portal_badge_school' : 'sms.landing.tenant.portal_badge_academy');
@endphp

<div class="space-y-10">
    <!-- Branded Campus Hero -->
    <section class="rounded-3xl bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 text-white p-8 sm:p-12 shadow-lg">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-8">
            <div class="max-w-2xl space-y-5">
                <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold bg-white/10 text-indigo-200 border border-white/15">
                    <span>{{ $tenant->isSchool() ? '🏫' : '🎓' }}</span>
                    <span>{{ __($portalBadgeKey) }}</span>
                </span>

                <div class="space-y-2">
                    <p class="text-sm font-semibold text-indigo-300 uppercase tracking-wider">
                        {{ __('sms.landing.tenant.welcome_prefix') }}
                    </p>
                    <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight leading-tight">
                        {{ $tenant->name }}
                    </h1>
                </div>

                <p class="text-slate-300 text-sm sm:text-base leading-relaxed">
                    {{ __('sms.landing.tenant.hero_subtitle') }}
                </p>

                <div class="flex flex-wrap items-center gap-3 pt-2">
                    <a href="{{ route('login') }}" class="inline-flex items-center justify-center px-6 py-3 rounded-xl bg-indigo-500 hover:bg-indigo-400 text-white font-bold text-sm shadow-md transition">
                        {{ __('sms.landing.tenant.cta_login') }}
                    </a>
                    <a href="#campus-services" class="inline-flex items-center justify-center px-6 py-3 rounded-xl bg-white/10 hover:bg-white/20 text-white border border-white/20 font-semibold text-sm transition">
                        {{ __('sms.landing.tenant.cta_explore') }}
                    </a>
                </div>
            </div>

            <!-- Campus Info Card -->
            <div class="bg-white/10 border border-white/15 rounded-2xl p-5 sm:p-6 space-y-3 min-w-[240px] backdrop-blur-xs">
                <div class="flex items-center gap-2 text-xs font-semibold text-emerald-300">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>{{ __('sms.landing.tenant.rls_badge') }}</span>
                </div>
                <div class="border-t border-white/10 pt-3 space-y-2 text-xs text-slate-200">
                    <div>
                        <span class="text-slate-400 block">{{ __('sms.landing.tenant.campus_label') }}</span>
                        <span class="font-bold text-white">
                            {{ $currentBranch ? $currentBranch->name . ($currentBranch->city ? " ({$currentBranch->city})" : '') : __('sms.landing.tenant.primary_campus') }}
                        </span>
                    </div>
                    <div>
                        <span class="text-slate-400 block">{{ __('sms.landing.tenant.workspace_label') }}</span>
                        <span class="font-mono text-indigo-200">{{ $tenant->slug }}</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Portal Services Overview -->
    <section id="campus-services" class="space-y-6">
        <div class="max-w-2xl">
            <h2 class="text-2xl font-bold text-slate-900">
                {{ __('sms.landing.tenant.highlights_heading') }}
            </h2>
            <p class="text-sm text-slate-500 mt-1">
                {{ __('sms.landing.tenant.highlights_subtitle') }}
            </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm space-y-2">
                <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-lg">🏛️</div>
                <h3 class="text-base font-bold text-slate-900">{{ __('sms.landing.tenant.card_academics_title') }}</h3>
                <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">{{ __('sms.landing.tenant.card_academics_desc') }}</p>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm space-y-2">
                <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center text-lg">👥</div>
                <h3 class="text-base font-bold text-slate-900">{{ __('sms.landing.tenant.card_students_title') }}</h3>
                <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">{{ __('sms.landing.tenant.card_students_desc') }}</p>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm space-y-2">
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg">🧾</div>
                <h3 class="text-base font-bold text-slate-900">{{ __('sms.landing.tenant.card_finance_title') }}</h3>
                <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">{{ __('sms.landing.tenant.card_finance_desc') }}</p>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm space-y-2">
                <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-lg">🎓</div>
                <h3 class="text-base font-bold text-slate-900">{{ __('sms.landing.tenant.card_exams_title') }}</h3>
                <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">{{ __('sms.landing.tenant.card_exams_desc') }}</p>
            </div>
        </div>
    </section>

    <!-- Staff Sign-In Strip -->
    <section class="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div class="space-y-1">
            <h3 class="text-lg font-bold text-slate-900">{{ __('sms.landing.tenant.staff_notice_title') }}</h3>
            <p class="text-sm text-slate-600">{{ __('sms.landing.tenant.staff_notice_desc') }}</p>
        </div>
        <a href="{{ route('login') }}" class="shrink-0 inline-flex items-center justify-center px-5 py-2.5 rounded-xl bg-slate-900 hover:bg-indigo-600 text-white font-bold text-sm transition">
            {{ __('sms.landing.tenant.cta_login') }}
        </a>
    </section>
</div>
@endsection
