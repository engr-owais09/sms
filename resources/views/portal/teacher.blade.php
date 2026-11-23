@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900">Teacher Workspace</h1>
            <p class="text-xs text-slate-500 mt-1">{{ $user->name }} • {{ $tenant->name }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('tenant.attendance.register') }}" class="px-4 py-2 bg-indigo-600 text-white text-xs font-bold rounded-lg">Mark Attendance</a>
            <a href="{{ route('tenant.exams.marks') }}" class="px-4 py-2 bg-slate-900 text-white text-xs font-bold rounded-lg">Enter Marks</a>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
        <div class="px-5 py-3 border-b border-slate-200 font-bold text-sm text-slate-900">My Weekly Timetable</div>
        <div class="overflow-x-auto">
            <table class="w-full text-start text-xs text-slate-600">
                <thead class="bg-slate-50 border-b border-slate-200 font-semibold">
                    <tr>
                        <th class="py-2.5 px-4 text-start">Day</th>
                        <th class="py-2.5 px-4 text-start">Period</th>
                        <th class="py-2.5 px-4 text-start">Class / Section</th>
                        <th class="py-2.5 px-4 text-start">Subject</th>
                        <th class="py-2.5 px-4 text-start">Room</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($timetable as $slot)
                        <tr>
                            <td class="py-2.5 px-4 font-semibold">{{ __('sms.timetable.days.' . $slot->day_of_week) }}</td>
                            <td class="py-2.5 px-4">{{ $slot->period->label ?? '—' }}</td>
                            <td class="py-2.5 px-4">{{ $slot->section->schoolClass->name ?? '' }} - {{ $slot->section->name ?? '' }}</td>
                            <td class="py-2.5 px-4 font-bold text-slate-900">{{ $slot->subject->name ?? '—' }}</td>
                            <td class="py-2.5 px-4 font-mono">{{ $slot->room->room_code ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-slate-400">No timetable slots assigned yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
