<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">
                {{ $isRtl ? 'حاضری کے تجزیات اور رپورٹس' : 'Attendance Analytics & Chronic Absentees' }}
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                {{ $isRtl ? 'مجموعی حاضری کا خلاصہ اور 75% سے کم حاضری والے طلباء' : 'Campus attendance summary and automated low-attendance alerts' }}
            </p>
        </div>

        <button type="button" wire:click="exportChronicAbsenteesCsv" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-bold shadow-sm">
            📥 {{ $isRtl ? 'CSV ایکسپورٹ' : 'Export Low-Attendance CSV' }}
        </button>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
            <span class="text-xs uppercase font-semibold text-emerald-600 block">Present</span>
            <span class="text-2xl font-bold text-slate-900 mt-1 block">{{ $analytics['summary']['present'] }}</span>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
            <span class="text-xs uppercase font-semibold text-rose-600 block">Absent</span>
            <span class="text-2xl font-bold text-slate-900 mt-1 block">{{ $analytics['summary']['absent'] }}</span>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
            <span class="text-xs uppercase font-semibold text-amber-600 block">Late</span>
            <span class="text-2xl font-bold text-slate-900 mt-1 block">{{ $analytics['summary']['late'] }}</span>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
            <span class="text-xs uppercase font-semibold text-indigo-600 block">Excused</span>
            <span class="text-2xl font-bold text-slate-900 mt-1 block">{{ $analytics['summary']['excused'] }}</span>
        </div>
    </div>

    <!-- Chronic Absentees Table -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-4 border-b border-slate-200 bg-slate-50">
            <h2 class="font-bold text-slate-900 text-sm">Students Below {{ $thresholdPercentage }}% Attendance</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50 uppercase font-semibold text-slate-500 border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-4">Admission #</th>
                        <th class="py-3 px-4">Student Name</th>
                        <th class="py-3 px-4 text-center">Attended / Total</th>
                        <th class="py-3 px-4 text-right">Attendance Rate</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($analytics['chronicAbsentees'] as $row)
                        <tr class="hover:bg-slate-50">
                            <td class="py-2.5 px-4 font-mono font-bold text-slate-900">{{ $row['admission_number'] }}</td>
                            <td class="py-2.5 px-4 font-semibold">{{ $row['full_name'] }}</td>
                            <td class="py-2.5 px-4 text-center">{{ $row['attended_days'] }} / {{ $row['total_days'] }}</td>
                            <td class="py-2.5 px-4 text-right font-bold text-rose-600">{{ $row['percentage'] }}%</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-8 text-center text-slate-400">No students below {{ $thresholdPercentage }}% threshold.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
