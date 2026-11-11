@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Workspace Banner -->
    <div class="bg-gradient-to-r from-slate-900 to-indigo-950 text-white rounded-2xl p-6 sm:p-8 shadow-sm">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-white/10 text-indigo-300 backdrop-blur-sm mb-3">
                    {{ $tenant->isSchool() ? '🏫 K-12 Formal School' : '🎓 Academy & Coaching Center' }}
                </span>
                <h1 class="text-3xl font-extrabold tracking-tight">{{ $tenant->name }}</h1>
                <p class="text-slate-300 text-sm mt-1">
                    {{ $currentBranch ? "Campus: {$currentBranch->name} ({$currentBranch->city})" : 'Primary Workspace' }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    {{ $isRtl ? 'محفوظ ملٹی ٹیننٹ RLS فعال' : 'Database RLS Protected' }}
                </span>
            </div>
        </div>
    </div>

    <!-- Dynamic Metrics by Institute Model -->
    @if($tenant->isSchool())
        <!-- SCHOOL METRICS -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $isRtl ? 'تعلیمی سیشن' : 'Academic Session' }}</span>
                <div class="mt-2 text-xl font-bold text-slate-900">{{ $tenant->settings['session_name'] ?? '2026-2027' }}</div>
                <span class="text-xs text-slate-400 mt-1 block">Active Annual Cycle</span>
            </div>

            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $isRtl ? 'کلاسز اور سیکشنز' : 'Pre-Seeded Grades' }}</span>
                <div class="mt-2 text-2xl font-bold text-indigo-600">
                    {{ count($tenant->settings['selected_templates'] ?? [1, 2, 3, 4]) }} Cohorts
                </div>
                <span class="text-xs text-emerald-600 font-semibold mt-1 block">✓ Class Structure Ready</span>
            </div>

            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $isRtl ? 'آج کی حاضری' : "Today's Attendance" }}</span>
                <div class="mt-2 text-2xl font-bold text-slate-900">Homeroom Grid</div>
                <span class="text-xs text-indigo-600 font-semibold mt-1 block">Ready for Morning Call</span>
            </div>

            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $isRtl ? 'فیس چالان سائیکل' : 'Fee Voucher Cycle' }}</span>
                <div class="mt-2 text-2xl font-bold text-slate-900">Due: {{ $tenant->settings['fee_due_day'] ?? 10 }}th</div>
                <span class="text-xs text-slate-400 mt-1 block">Pakistani 3-Copy Vouchers</span>
            </div>
        </div>
    @else
        <!-- ACADEMY METRICS -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $isRtl ? 'فعال پروگرامز' : 'Programs & Tracks' }}</span>
                <div class="mt-2 text-2xl font-bold text-purple-600">
                    {{ count($tenant->settings['selected_templates'] ?? [1, 2, 3, 4]) }} Tracks
                </div>
                <span class="text-xs text-emerald-600 font-semibold mt-1 block">Entry Test & Coaching</span>
            </div>

            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $isRtl ? 'فعال بیجز' : 'Rolling Batches' }}</span>
                <div class="mt-2 text-2xl font-bold text-slate-900">Dynamic Enrollment</div>
                <span class="text-xs text-slate-400 mt-1 block">Multiple simultaneous batches</span>
            </div>

            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $isRtl ? 'سیشن حاضری' : 'Session Attendance' }}</span>
                <div class="mt-2 text-2xl font-bold text-slate-900">Slot-Wise</div>
                <span class="text-xs text-indigo-600 font-semibold mt-1 block">Per-lecture tracking</span>
            </div>

            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $isRtl ? 'قسط وار فیس ماڈل' : 'Installment Engine' }}</span>
                <div class="mt-2 text-2xl font-bold text-slate-900">Course / Installment</div>
                <span class="text-xs text-slate-400 mt-1 block">Flexible installment schedules</span>
            </div>
        </div>
    @endif

    <!-- Starter Guidance Card -->
    <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
        <h3 class="text-lg font-bold text-slate-900 mb-2">
            {{ $isRtl ? 'آپ کا ورک اسپیس مکمل طور پر فعال ہے' : 'Your Isolated Workspace is Active' }}
        </h3>
        <p class="text-sm text-slate-600 mb-4">
            {{ $isRtl ? 'تمام ڈیٹا PostgreSQL Row-Level Security اور ٹیننٹ اسکوپنگ کے ذریعے مکمل طور پر محفوظ ہے۔' : 'All queries within this portal are cryptographically isolated at the database level with PostgreSQL RLS and Eloquent scopes.' }}
        </p>

        <div class="flex flex-wrap gap-3">
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-100 text-slate-700 text-xs font-medium">
                🔑 {{ $isRtl ? 'کردار: انسٹیٹیوٹ اونر' : 'Role: Institute Owner' }}
            </span>
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-100 text-slate-700 text-xs font-medium">
                🌐 {{ $isRtl ? 'سب ڈومین: ' : 'Subdomain: ' }} {{ $tenant->slug }}.educore.test
            </span>
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-100 text-slate-700 text-xs font-medium">
                🏢 {{ $isRtl ? 'مین کیمپس: ' : 'Campus: ' }} {{ $currentBranch?->name ?? 'Main' }}
            </span>
        </div>
    </div>
</div>
@endsection
