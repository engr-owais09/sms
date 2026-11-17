<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">
                {{ $isRtl ? 'حاضری رجسٹر (رول کال)' : 'Roll Call Attendance Register' }}
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                {{ $isRtl ? 'روزانہ اسکول حاضری اور اکیڈمی لیکچر حاضری' : 'Fast daily school roll call and academy batch lecture attendance' }}
            </p>
        </div>

        <div class="flex items-center gap-2">
            <button type="button" wire:click="markAllPresent" class="px-3 py-2 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-lg text-xs font-bold hover:bg-emerald-100">
                ✓ {{ $isRtl ? 'سب کو حاضر کریں' : 'Mark All Present' }}
            </button>
        </div>
    </div>

    @if($successMessage)
        <div class="bg-emerald-50 border border-emerald-300 text-emerald-900 px-4 py-3 rounded-lg text-sm">✓ {{ $successMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="bg-rose-50 border border-rose-300 text-rose-900 px-4 py-3 rounded-lg text-sm">✕ {{ $errorMessage }}</div>
    @endif

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <div>
            <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Mode</label>
            <select wire:model.live="domainMode" wire:change="loadRoster" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                <option value="school">School Section</option>
                <option value="academy">Academy Batch</option>
            </select>
        </div>

        @if($domainMode === 'school')
            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Section</label>
                <select wire:model.live="schoolSectionId" wire:change="loadRoster" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                    @foreach($sections as $sec)
                        <option value="{{ $sec->id }}">{{ $sec->schoolClass->name ?? '' }} - {{ $sec->name }}</option>
                    @endforeach
                </select>
            </div>
        @else
            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Academy Batch</label>
                <select wire:model.live="academyBatchId" wire:change="loadRoster" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                    @foreach($batches as $b)
                        <option value="{{ $b->id }}">{{ $b->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div>
            <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Date</label>
            <input type="date" wire:model.live="attendanceDate" wire:change="loadRoster" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
        </div>

        <div>
            <button type="button" wire:click="loadRoster" class="w-full py-2 px-4 bg-slate-800 hover:bg-slate-900 text-white font-bold rounded-lg text-sm">
                {{ $isRtl ? 'فہرست لوڈ کریں' : 'Load Roster' }}
            </button>
        </div>
    </div>

    <!-- Roster Table -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50 uppercase font-semibold text-slate-500 border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-4">Admission #</th>
                        <th class="py-3 px-4">Student Name</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4">Remarks</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($roster as $idx => $row)
                        <tr class="hover:bg-slate-50">
                            <td class="py-3 px-4 font-mono font-bold text-slate-900">{{ $row['admission_number'] }}</td>
                            <td class="py-3 px-4 font-semibold text-slate-800">{{ $row['full_name'] }}</td>
                            <td class="py-3 px-4">
                                <select wire:model.live="roster.{{ $idx }}.status" class="px-2.5 py-1 border border-slate-300 rounded text-xs font-semibold">
                                    <option value="present">Present</option>
                                    <option value="absent">Absent</option>
                                    <option value="late">Late</option>
                                    <option value="excused">Excused</option>
                                    @if($domainMode === 'school')
                                        <option value="half_day">Half Day</option>
                                    @endif
                                </select>
                            </td>
                            <td class="py-3 px-4">
                                <input type="text" wire:model.live="roster.{{ $idx }}.remarks" placeholder="Optional note..." class="w-full px-2.5 py-1 border border-slate-300 rounded text-xs">
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-8 text-center text-slate-400">No enrolled students found for selection.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(!empty($roster))
            <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end">
                <button type="button" wire:click="saveAttendance" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg text-sm shadow-sm">
                    ✓ {{ $isRtl ? 'حاضری محفوظ اور جمع کروائیں' : 'Save & Submit Attendance' }}
                </button>
            </div>
        @endif
    </div>
</div>
