<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <h1 class="text-2xl font-bold text-slate-900">
            {{ $isRtl ? 'امتحانی شیڈول اور ڈیٹ شیٹ مینیجر' : 'Exam Terms & Date Sheet Manager' }}
        </h1>
        <p class="text-sm text-slate-500 mt-1">
            {{ $isRtl ? 'امتحانی ٹرمز اور مضمون وار نمبروں کی ترتیب' : 'Configure examination terms, weightages, and subject date sheets' }}
        </p>
    </div>

    @if($successMessage)
        <div class="bg-emerald-50 border border-emerald-300 text-emerald-900 px-4 py-3 rounded-lg text-sm">✓ {{ $successMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="bg-rose-50 border border-rose-300 text-rose-900 px-4 py-3 rounded-lg text-sm">✕ {{ $errorMessage }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="space-y-6">
            <!-- Create Exam Term -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-3">
                <h2 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-2">Create Exam Term</h2>
                <input type="text" wire:model.live="termName" placeholder="e.g. Final Term 2026" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                <div class="grid grid-cols-2 gap-2">
                    <select wire:model.live="termType" class="px-3 py-2 text-xs border border-slate-300 rounded-lg">
                        <option value="unit_test">Unit Test</option>
                        <option value="midterm">Midterm</option>
                        <option value="final">Final</option>
                        <option value="annual">Annual</option>
                    </select>
                    <input type="number" step="5" wire:model.live="weightagePercentage" placeholder="Weight %" class="px-3 py-2 text-xs border border-slate-300 rounded-lg">
                </div>
                <button type="button" wire:click="createTerm" class="w-full py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg text-xs">
                    Save Exam Term
                </button>
            </div>

            <!-- Add Paper to Date Sheet -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-3">
                <h2 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-2">Schedule Subject Paper</h2>
                <select wire:model.live="selectedTermId" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg">
                    @foreach($terms as $t)
                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                    @endforeach
                </select>
                <select wire:model.live="paperClassId" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg">
                    @foreach($classes as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
                <select wire:model.live="paperSubjectId" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg">
                    @foreach($subjects as $sub)
                        <option value="{{ $sub->id }}">{{ $sub->name }}</option>
                    @endforeach
                </select>
                <div class="grid grid-cols-3 gap-2">
                    <input type="date" wire:model.live="examDate" class="px-2 py-1.5 text-xs border border-slate-300 rounded">
                    <input type="number" wire:model.live="maxMarks" placeholder="Max" class="px-2 py-1.5 text-xs border border-slate-300 rounded">
                    <input type="number" wire:model.live="passingMarks" placeholder="Pass" class="px-2 py-1.5 text-xs border border-slate-300 rounded">
                </div>
                <button type="button" wire:click="addPaper" class="w-full py-2 bg-slate-800 hover:bg-slate-900 text-white font-bold rounded-lg text-xs">
                    Add Paper to Date Sheet
                </button>
            </div>
        </div>

        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-4 border-b border-slate-200 bg-slate-50">
                <h2 class="font-bold text-slate-900 text-sm">Scheduled Papers (Date Sheet)</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600">
                    <thead class="bg-slate-50 uppercase font-semibold text-slate-500 border-b border-slate-200">
                        <tr>
                            <th class="py-3 px-4">Date</th>
                            <th class="py-3 px-4">Class</th>
                            <th class="py-3 px-4">Subject</th>
                            <th class="py-3 px-4 text-right">Max Marks</th>
                            <th class="py-3 px-4 text-right">Passing</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($papers as $p)
                            <tr class="hover:bg-slate-50">
                                <td class="py-2.5 px-4 font-mono">{{ $p->exam_date->format('Y-m-d') }}</td>
                                <td class="py-2.5 px-4">{{ $p->schoolClass->name ?? '' }}</td>
                                <td class="py-2.5 px-4 font-bold text-slate-900">{{ $p->subject->name ?? '' }}</td>
                                <td class="py-2.5 px-4 text-right font-mono">{{ number_format($p->max_marks, 0) }}</td>
                                <td class="py-2.5 px-4 text-right font-mono">{{ number_format($p->passing_marks, 0) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-slate-400">No papers scheduled for selected term.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
