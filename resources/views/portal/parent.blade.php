@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900">
                {{ ($currentLocale ?? 'en') === 'ur' ? 'والدین پورٹل' : 'Parent & Guardian Portal' }}
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-1">{{ $tenant->name }}</p>
        </div>

        @if($children->count() > 1)
            <form method="GET" action="{{ route('portal.parent') }}" class="flex items-center gap-2">
                <select name="student_id" onchange="this.form.submit()" class="px-3 py-2 text-xs font-semibold border border-slate-300 rounded-lg">
                    @foreach($children as $child)
                        <option value="{{ $child->id }}" @selected($activeChild?->id === $child->id)>
                            {{ $child->full_name }} ({{ $child->admission_number }})
                        </option>
                    @endforeach
                </select>
            </form>
        @endif
    </div>

    @if(session('status'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg text-sm">✓ {{ session('status') }}</div>
    @endif

    @if($activeChild)
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <!-- Child Summary -->
                <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
                    <div class="font-bold text-lg text-slate-900">{{ $activeChild->full_name }}</div>
                    <div class="text-xs text-slate-500 font-mono mt-1">{{ $activeChild->admission_number }}</div>
                    <div class="grid grid-cols-3 gap-3 mt-4 text-center">
                        <div class="p-3 rounded-lg bg-emerald-50 text-emerald-800">
                            <div class="text-lg font-bold">{{ $attendanceSummary['present'] ?? 0 }}</div>
                            <div class="text-[11px] font-semibold">Present</div>
                        </div>
                        <div class="p-3 rounded-lg bg-rose-50 text-rose-800">
                            <div class="text-lg font-bold">{{ $attendanceSummary['absent'] ?? 0 }}</div>
                            <div class="text-[11px] font-semibold">Absent</div>
                        </div>
                        <div class="p-3 rounded-lg bg-amber-50 text-amber-800">
                            <div class="text-lg font-bold">{{ $attendanceSummary['leave'] ?? 0 }}</div>
                            <div class="text-[11px] font-semibold">Leave</div>
                        </div>
                    </div>
                </div>

                <!-- Fee Vouchers -->
                <div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
                    <div class="px-5 py-3 border-b border-slate-200 font-bold text-sm text-slate-900">Fee Vouchers</div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-start text-xs text-slate-600">
                            <thead class="bg-slate-50 border-b border-slate-200 font-semibold">
                                <tr>
                                    <th class="py-2.5 px-4 text-start">Invoice #</th>
                                    <th class="py-2.5 px-4 text-start">Period</th>
                                    <th class="py-2.5 px-4 text-end">Total</th>
                                    <th class="py-2.5 px-4 text-start">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($invoices as $inv)
                                    <tr>
                                        <td class="py-2.5 px-4 font-mono font-bold text-indigo-700">{{ $inv->invoice_number }}</td>
                                        <td class="py-2.5 px-4">{{ $inv->billing_period }}</td>
                                        <td class="py-2.5 px-4 text-end font-mono">PKR {{ number_format((float) $inv->total_amount, 2) }}</td>
                                        <td class="py-2.5 px-4 uppercase font-bold">{{ $inv->status }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="py-6 text-center text-slate-400">No vouchers issued yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <!-- Leave Request Form -->
                <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm space-y-3">
                    <h2 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2">Apply for Student Leave</h2>
                    <form method="POST" action="{{ route('portal.parent.leave') }}" class="space-y-3">
                        @csrf
                        <input type="hidden" name="student_id" value="{{ $activeChild->id }}">
                        <div class="grid grid-cols-2 gap-2">
                            <input type="date" name="from_date" required class="px-3 py-2 text-xs border border-slate-300 rounded-lg">
                            <input type="date" name="to_date" required class="px-3 py-2 text-xs border border-slate-300 rounded-lg">
                        </div>
                        <input type="text" name="reason" required placeholder="Reason for leave" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg">
                        <button type="submit" class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg">
                            Submit Leave Application
                        </button>
                    </form>
                </div>

                <!-- Notices -->
                <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm space-y-3">
                    <h2 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2">Campus Notices</h2>
                    @forelse($notices as $notice)
                        <div class="p-3 rounded-lg bg-slate-50 border border-slate-100 space-y-1">
                            <div class="text-xs font-bold text-slate-900">
                                {{ ($currentLocale ?? 'en') === 'ur' && $notice->title_ur ? $notice->title_ur : $notice->title }}
                            </div>
                            <p class="text-xs text-slate-600">
                                {{ ($currentLocale ?? 'en') === 'ur' && $notice->body_ur ? $notice->body_ur : $notice->body }}
                            </p>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400">No announcements.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @else
        <div class="bg-white rounded-xl border border-slate-200 p-8 text-center text-sm text-slate-500">
            No linked student records found for this guardian account.
        </div>
    @endif
</div>
@endsection
