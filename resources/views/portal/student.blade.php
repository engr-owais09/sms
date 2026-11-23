@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
        <h1 class="text-xl sm:text-2xl font-bold text-slate-900">{{ $student->full_name }}</h1>
        <p class="text-xs text-slate-500 font-mono mt-1">{{ $student->admission_number }} • {{ $tenant->name }}</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm space-y-3">
            <h2 class="text-sm font-bold text-slate-900">Attendance Summary</h2>
            <div class="grid grid-cols-3 gap-3 text-center">
                <div class="p-3 rounded-lg bg-emerald-50 text-emerald-800">
                    <div class="text-lg font-bold">{{ $attendanceSummary['present'] ?? 0 }}</div>
                    <div class="text-[11px]">Present</div>
                </div>
                <div class="p-3 rounded-lg bg-rose-50 text-rose-800">
                    <div class="text-lg font-bold">{{ $attendanceSummary['absent'] ?? 0 }}</div>
                    <div class="text-[11px]">Absent</div>
                </div>
                <div class="p-3 rounded-lg bg-amber-50 text-amber-800">
                    <div class="text-lg font-bold">{{ $attendanceSummary['leave'] ?? 0 }}</div>
                    <div class="text-[11px]">Leave</div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm space-y-3">
            <h2 class="text-sm font-bold text-slate-900">Notices</h2>
            @forelse($notices as $notice)
                <div class="p-3 rounded-lg bg-slate-50 border border-slate-100 text-xs">
                    <div class="font-bold text-slate-900">{{ $notice->title }}</div>
                    <p class="text-slate-600 mt-0.5">{{ $notice->body }}</p>
                </div>
            @empty
                <p class="text-xs text-slate-400">No announcements.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
