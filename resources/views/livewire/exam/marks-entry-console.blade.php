<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <h1 class="text-2xl font-bold text-slate-900">
            {{ $isRtl ? 'امتحانی نمبرات کا اندراج' : 'Exam Marks Entry Console' }}
        </h1>
        <p class="text-sm text-slate-500 mt-1">
            {{ $isRtl ? 'مضمون اور سیکشن کے مطابق طلباء کے نمبرات درج کریں' : 'Enter subject marks with automatic max-marks validation and absent tracking' }}
        </p>
    </div>

    @if($successMessage)
        <div class="bg-emerald-50 border border-emerald-300 text-emerald-900 px-4 py-3 rounded-lg text-sm">✓ {{ $successMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="bg-rose-50 border border-rose-300 text-rose-900 px-4 py-3 rounded-lg text-sm">✕ {{ $errorMessage }}</div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <div>
            <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Exam Term</label>
            <select wire:model.live="selectedTermId" wire:change="loadStudents" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                @foreach($terms as $t)
                    <option value="{{ $t->id }}">{{ $t->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Paper / Subject</label>
            <select wire:model.live="selectedPaperId" wire:change="loadStudents" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                @foreach($papers as $p)
                    <option value="{{ $p->id }}">{{ $p->subject->name ?? '' }} (Max {{ number_format($p->max_marks, 0) }})</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Section</label>
            <select wire:model.live="selectedSectionId" wire:change="loadStudents" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                @foreach($sections as $s)
                    <option value="{{ $s->id }}">{{ $s->schoolClass->name ?? '' }} - {{ $s->name }}</option>
                @endforeach
            </select>
        </div>

        <button type="button" wire:click="loadStudents" class="py-2 px-4 bg-slate-800 hover:bg-slate-900 text-white font-bold rounded-lg text-sm">
            Load Students
        </button>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50 uppercase font-semibold text-slate-500 border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-4">Admission #</th>
                        <th class="py-3 px-4">Student Name</th>
                        <th class="py-3 px-4">Absent</th>
                        <th class="py-3 px-4">Marks Obtained</th>
                        <th class="py-3 px-4">Remarks</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($markRows as $idx => $row)
                        <tr class="hover:bg-slate-50">
                            <td class="py-2.5 px-4 font-mono font-bold text-slate-900">{{ $row['admission_number'] }}</td>
                            <td class="py-2.5 px-4 font-semibold">{{ $row['full_name'] }}</td>
                            <td class="py-2.5 px-4">
                                <input type="checkbox" wire:model.live="markRows.{{ $idx }}.is_absent" class="rounded text-rose-600">
                            </td>
                            <td class="py-2.5 px-4">
                                <input type="number" step="0.5" wire:model.live="markRows.{{ $idx }}.marks" {{ $row['is_absent'] ? 'disabled' : '' }} class="w-28 px-2.5 py-1 border border-slate-300 rounded font-mono font-bold text-right">
                            </td>
                            <td class="py-2.5 px-4">
                                <input type="text" wire:model.live="markRows.{{ $idx }}.remarks" class="w-full px-2.5 py-1 border border-slate-300 rounded">
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-slate-400">No students loaded.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(!empty($markRows))
            <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end">
                <button type="button" wire:click="saveMarks" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg text-sm">
                    ✓ Save & Submit Marks
                </button>
            </div>
        @endif
    </div>
</div>
