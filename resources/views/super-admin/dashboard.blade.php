@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">
                {{ $isRtl ? 'سپر ایڈمن پلیٹ فارم کنٹرول پینل' : 'Super Admin Platform Control Plane' }}
            </h1>
            <p class="text-sm text-slate-500">
                {{ $isRtl ? 'تمام ملٹی ٹیننٹ اداروں، ایکٹیویشن اور سبسکرپشنز کا مرکزی جائزہ۔' : 'Central overview of all multi-tenant educational workspaces and subscriptions.' }}
            </p>
        </div>

        <a href="{{ route('onboarding.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-indigo-600 text-white font-semibold text-sm hover:bg-indigo-700 shadow-sm transition-all">
            <span>+</span>
            <span>{{ $isRtl ? 'نیا ادارہ شامل کریں' : 'Onboard New Institute' }}</span>
        </a>
    </div>

    <!-- Metrics Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $isRtl ? 'کل تعلیمی ادارے' : 'Total Institutes' }}</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-3xl font-bold text-slate-900">{{ $totalTenants }}</span>
                <span class="text-xs font-medium text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded">All Registered</span>
            </div>
        </div>

        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $isRtl ? 'فعال ادارے' : 'Active Workspaces' }}</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-3xl font-bold text-emerald-600">{{ $activeTenants }}</span>
                <span class="text-xs font-medium text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded">Running</span>
            </div>
        </div>

        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $isRtl ? 'معطل ادارے' : 'Suspended' }}</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-3xl font-bold text-rose-600">{{ $suspendedTenants }}</span>
                <span class="text-xs font-medium text-rose-700 bg-rose-50 px-2 py-0.5 rounded">Access Locked</span>
            </div>
        </div>

        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $isRtl ? 'کل کیمپسز / برانچز' : 'Total Campuses' }}</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-3xl font-bold text-indigo-600">{{ $totalBranches }}</span>
                <span class="text-xs font-medium text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded">Campuses</span>
            </div>
        </div>
    </div>

    <!-- Tenants Table -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
            <h2 class="font-bold text-slate-800 text-base">
                {{ $isRtl ? 'رجسٹرڈ تعلیمی ادارے اور پورٹلز' : 'Registered Educational Institutes' }}
            </h2>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-start text-sm">
                <thead class="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-3.5 text-start">{{ $isRtl ? 'ادارے کا نام اور قسم' : 'Institute & Type' }}</th>
                        <th class="px-6 py-3.5 text-start">{{ $isRtl ? 'سب ڈومین' : 'Subdomain / Domain' }}</th>
                        <th class="px-6 py-3.5 text-start">{{ $isRtl ? 'کیمپسز' : 'Branches' }}</th>
                        <th class="px-6 py-3.5 text-start">{{ $isRtl ? 'حالت' : 'Status' }}</th>
                        <th class="px-6 py-3.5 text-start">{{ $isRtl ? 'آن بورڈنگ' : 'Onboarding' }}</th>
                        <th class="px-6 py-3.5 text-end">{{ $isRtl ? 'ایکشن' : 'Action' }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($tenants as $t)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-900">{{ $t->name }}</div>
                                <div class="text-xs text-slate-500 uppercase tracking-wider mt-0.5">
                                    {{ $t->type === 'school' ? '🏫 School' : '🎓 Academy' }}
                                </div>
                            </td>
                            <td class="px-6 py-4 font-mono text-xs text-indigo-600">
                                <a href="http://{{ $t->slug }}.educore.test:8000" target="_blank" class="hover:underline">
                                    {{ $t->slug }}.educore.test
                                </a>
                            </td>
                            <td class="px-6 py-4 text-slate-600 font-medium">
                                {{ $t->branches->count() }} Campus(es)
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $t->status === 'active' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : ($t->status === 'trial' ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-rose-50 text-rose-700 border border-rose-200') }}">
                                    {{ ucfirst($t->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-xs">
                                @if($t->onboarding_completed)
                                    <span class="text-emerald-600 font-semibold">✓ Completed</span>
                                @else
                                    <span class="text-amber-600 font-semibold">Step {{ $t->onboarding_step }} of 9</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-end">
                                <form action="{{ route('super-admin.tenants.toggle-status', $t) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold px-3 py-1 rounded border {{ $t->status === 'active' ? 'border-rose-200 text-rose-600 hover:bg-rose-50' : 'border-emerald-200 text-emerald-600 hover:bg-emerald-50' }}">
                                        {{ $t->status === 'active' ? ($isRtl ? 'معطل کریں' : 'Suspend') : ($isRtl ? 'بحال کریں' : 'Activate') }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-slate-500">
                                {{ $isRtl ? 'ابھی تک کوئی تعلیمی ادارہ شامل نہیں کیا گیا ہے۔' : 'No educational institutes registered yet.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 border-t border-slate-200">
            {{ $tenants->links() }}
        </div>
    </div>
</div>
@endsection
