<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">
                {{ $isRtl ? 'نتائج کی تیاری اور رزلٹ کارڈ پورٹل' : 'Result Tabulation & Report Cards' }}
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                {{ $isRtl ? 'پوزیشن، گریڈز، براڈ شیٹ اور دو لسانی رزلٹ کارڈز' : 'Compute section ranks, publish term results, export broadsheets, and view bilingual report cards' }}
            </p>
        </div>

        <button type="button" wire:click="exportBroadsheetCsv" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-lg text-xs font-bold">
            📥 Export Broadsheet CSV
        </button>
    </div>

    @if($successMessage)
        <div class="bg-emerald-50 border border-emerald-300 text-emerald-900 px-4 py-3 rounded-lg text-sm">✓ {{ $successMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="bg-rose-50 border border-rose-300 text-rose-900 px-4 py-3 rounded-lg text-sm">✕ {{ $errorMessage }}</div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
        <div>
            <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Exam Term</label>
            <select wire:model.live="selectedTermId" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                @foreach($terms as $t)
                    <option value="{{ $t->id }}">{{ $t->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Section</label>
            <select wire:model.live="selectedSectionId" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                @foreach($sections as $s)
                    <option value="{{ $s->id }}">{{ $s->schoolClass->name ?? '' }} - {{ $s->name }}</option>
                @endforeach
            </select>
        </div>

        <button type="button" wire:click="tabulateAndPublish" class="py-2.5 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg text-sm">
            ⚡ Tabulate & Publish Results
        </button>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50 uppercase font-semibold text-slate-500 border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-4">Rank</th>
                        <th class="py-3 px-4">Admission #</th>
                        <th class="py-3 px-4">Student</th>
                        <th class="py-3 px-4 text-right">Obtained / Max</th>
                        <th class="py-3 px-4 text-right">%</th>
                        <th class="py-3 px-4 text-center">Grade</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-center">Report Card</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($results as $res)
                        <tr class="hover:bg-slate-50">
                            <td class="py-2.5 px-4 font-bold text-indigo-700">#{{ $res->section_rank }}</td>
                            <td class="py-2.5 px-4 font-mono">{{ $res->student->admission_number ?? '' }}</td>
                            <td class="py-2.5 px-4 font-semibold text-slate-900">{{ $res->student->first_name ?? '' }} {{ $res->student->last_name ?? '' }}</td>
                            <td class="py-2.5 px-4 text-right font-mono">{{ number_format($res->total_obtained_marks, 2) }} / {{ number_format($res->total_max_marks, 2) }}</td>
                            <td class="py-2.5 px-4 text-right font-bold">{{ number_format($res->percentage, 2) }}%</td>
                            <td class="py-2.5 px-4 text-center font-bold">{{ $res->grade_letter }}</td>
                            <td class="py-2.5 px-4 text-center">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $res->result_status === 'pass' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                    {{ $res->result_status }}
                                </span>
                            </td>
                            <td class="py-2.5 px-4 text-center space-x-1">
                                <a href="{{ route('tenant.exams.report-card', $res) }}" target="_blank" class="px-2 py-0.5 bg-slate-100 hover:bg-slate-200 rounded text-[10px] font-semibold">🖨️ View</a>
                                <a href="{{ route('tenant.exams.report-card', ['result' => $res, 'format' => 'pdf']) }}" target="_blank" class="px-2 py-0.5 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 rounded text-[10px] font-semibold">PDF</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-slate-400">No results tabulated yet. Click "Tabulate & Publish Results".</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
