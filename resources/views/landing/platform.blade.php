@extends('layouts.app')

@section('content')
<div class="space-y-12">
    <!-- Hero Section -->
    <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 text-white p-8 sm:p-12 lg:p-16 shadow-lg">
        <div class="max-w-3xl space-y-6">
            <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold bg-indigo-500/20 text-indigo-200 border border-indigo-400/30">
                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                {{ __('sms.landing.platform.badge') }}
            </span>

            <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight leading-tight">
                {{ __('sms.landing.platform.hero_title') }}
            </h1>

            <p class="text-slate-300 text-base sm:text-lg leading-relaxed max-w-2xl">
                {{ __('sms.landing.platform.hero_subtitle') }}
            </p>

            <div class="flex flex-wrap items-center gap-3 pt-2">
                <a href="{{ route('onboarding.index') }}" class="inline-flex items-center justify-center px-6 py-3 rounded-xl bg-indigo-500 hover:bg-indigo-400 text-white font-bold text-sm shadow-md transition">
                    {{ __('sms.landing.platform.cta_onboard') }}
                </a>
                <a href="{{ route('login') }}" class="inline-flex items-center justify-center px-6 py-3 rounded-xl bg-white/10 hover:bg-white/20 text-white border border-white/20 font-semibold text-sm transition">
                    {{ __('sms.landing.platform.cta_signin') }}
                </a>
            </div>

            <div class="flex flex-wrap items-center gap-2 pt-4 text-xs text-slate-300">
                <span class="px-3 py-1.5 rounded-lg bg-white/5 border border-white/10">✓ {{ __('sms.landing.platform.trust_rls') }}</span>
                <span class="px-3 py-1.5 rounded-lg bg-white/5 border border-white/10">✓ {{ __('sms.landing.platform.trust_rtl') }}</span>
                <span class="px-3 py-1.5 rounded-lg bg-white/5 border border-white/10">✓ {{ __('sms.landing.platform.trust_vouchers') }}</span>
                <span class="px-3 py-1.5 rounded-lg bg-white/5 border border-white/10">✓ {{ __('sms.landing.platform.trust_hybrid') }}</span>
            </div>
        </div>
    </section>

    <!-- Core Capabilities -->
    <section class="space-y-6">
        <div class="text-center max-w-2xl mx-auto">
            <h2 class="text-2xl sm:text-3xl font-bold text-slate-900">
                {{ __('sms.landing.platform.features_heading') }}
            </h2>
            <p class="text-sm sm:text-base text-slate-500 mt-2">
                {{ __('sms.landing.platform.features_subtitle') }}
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-2">
                <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-lg">👥</div>
                <h3 class="text-base font-bold text-slate-900">{{ __('sms.landing.platform.feat_students_title') }}</h3>
                <p class="text-sm text-slate-600 leading-relaxed">{{ __('sms.landing.platform.feat_students_desc') }}</p>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-2">
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg">💰</div>
                <h3 class="text-base font-bold text-slate-900">{{ __('sms.landing.platform.feat_finance_title') }}</h3>
                <p class="text-sm text-slate-600 leading-relaxed">{{ __('sms.landing.platform.feat_finance_desc') }}</p>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-2">
                <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-lg">🗓️</div>
                <h3 class="text-base font-bold text-slate-900">{{ __('sms.landing.platform.feat_attendance_title') }}</h3>
                <p class="text-sm text-slate-600 leading-relaxed">{{ __('sms.landing.platform.feat_attendance_desc') }}</p>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-2">
                <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-lg">🎓</div>
                <h3 class="text-base font-bold text-slate-900">{{ __('sms.landing.platform.feat_exams_title') }}</h3>
                <p class="text-sm text-slate-600 leading-relaxed">{{ __('sms.landing.platform.feat_exams_desc') }}</p>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-2">
                <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center text-lg">👔</div>
                <h3 class="text-base font-bold text-slate-900">{{ __('sms.landing.platform.feat_staff_title') }}</h3>
                <p class="text-sm text-slate-600 leading-relaxed">{{ __('sms.landing.platform.feat_staff_desc') }}</p>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-2">
                <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center text-lg">🛡️</div>
                <h3 class="text-base font-bold text-slate-900">{{ __('sms.landing.platform.feat_security_title') }}</h3>
                <p class="text-sm text-slate-600 leading-relaxed">{{ __('sms.landing.platform.feat_security_desc') }}</p>
            </div>
        </div>
    </section>

    <!-- Institute Models -->
    <section class="space-y-6">
        <div class="text-center max-w-2xl mx-auto">
            <h2 class="text-2xl sm:text-3xl font-bold text-slate-900">
                {{ __('sms.landing.platform.models_heading') }}
            </h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-3">
                <span class="inline-flex px-3 py-1 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700">🏫 School</span>
                <h3 class="text-lg font-bold text-slate-900">{{ __('sms.landing.platform.model_school_title') }}</h3>
                <p class="text-sm text-slate-600 leading-relaxed">{{ __('sms.landing.platform.model_school_desc') }}</p>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-3">
                <span class="inline-flex px-3 py-1 rounded-full text-xs font-bold bg-purple-50 text-purple-700">🎓 Academy</span>
                <h3 class="text-lg font-bold text-slate-900">{{ __('sms.landing.platform.model_academy_title') }}</h3>
                <p class="text-sm text-slate-600 leading-relaxed">{{ __('sms.landing.platform.model_academy_desc') }}</p>
            </div>

            <div class="bg-white rounded-2xl border-2 border-indigo-200 p-6 shadow-sm space-y-3">
                <span class="inline-flex px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700">⚡ Hybrid</span>
                <h3 class="text-lg font-bold text-slate-900">{{ __('sms.landing.platform.model_hybrid_title') }}</h3>
                <p class="text-sm text-slate-600 leading-relaxed">{{ __('sms.landing.platform.model_hybrid_desc') }}</p>
            </div>
        </div>
    </section>

    <!-- Pricing Plans -->
    @if(isset($plans) && $plans->count() > 0)
        <section class="space-y-6">
            <div class="text-center max-w-2xl mx-auto">
                <h2 class="text-2xl sm:text-3xl font-bold text-slate-900">
                    {{ __('sms.landing.platform.pricing_heading') }}
                </h2>
                <p class="text-sm sm:text-base text-slate-500 mt-2">
                    {{ __('sms.landing.platform.pricing_subtitle') }}
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                @foreach($plans as $plan)
                    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm flex flex-col justify-between space-y-5">
                        <div class="space-y-3">
                            <div class="text-sm font-bold text-indigo-600 uppercase tracking-wider">{{ $plan->name }}</div>
                            <div class="flex items-baseline gap-1">
                                <span class="text-3xl font-extrabold text-slate-900 font-mono">PKR {{ number_format((float) $plan->price_pkr) }}</span>
                                <span class="text-xs text-slate-500">{{ __('sms.landing.platform.per_month') }}</span>
                            </div>
                            @if($plan->description)
                                <p class="text-xs text-slate-600">{{ $plan->description }}</p>
                            @endif
                            <ul class="space-y-2 pt-2 text-xs text-slate-700 border-t border-slate-100">
                                <li>✓ {{ __('sms.landing.platform.up_to_branches', ['count' => $plan->max_branches]) }}</li>
                                <li>✓ {{ __('sms.landing.platform.up_to_students', ['count' => number_format((int) $plan->max_students)]) }}</li>
                                <li>✓ {{ __('sms.landing.platform.up_to_staff', ['count' => $plan->max_staff]) }}</li>
                            </ul>
                        </div>

                        <a href="{{ route('onboarding.index') }}" class="w-full inline-flex items-center justify-center px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-indigo-600 text-white text-xs font-bold transition">
                            {{ __('sms.landing.platform.choose_plan') }}
                        </a>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <!-- Bottom CTA -->
    <section class="rounded-2xl bg-indigo-600 text-white p-8 sm:p-10 flex flex-col md:flex-row items-start md:items-center justify-between gap-6 shadow-sm">
        <div class="space-y-1.5">
            <h2 class="text-2xl font-bold">{{ __('sms.landing.platform.cta_banner_title') }}</h2>
            <p class="text-indigo-100 text-sm">{{ __('sms.landing.platform.cta_banner_desc') }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-3 shrink-0">
            <a href="{{ route('onboarding.index') }}" class="px-5 py-3 rounded-xl bg-white text-indigo-700 hover:bg-indigo-50 font-bold text-sm shadow-sm transition">
                {{ __('sms.landing.platform.cta_onboard') }}
            </a>
            <a href="{{ route('login') }}" class="px-5 py-3 rounded-xl bg-indigo-700 hover:bg-indigo-800 text-white font-semibold text-sm border border-indigo-400/40 transition">
                {{ __('sms.landing.platform.cta_signin') }}
            </a>
        </div>
    </section>
</div>
@endsection
