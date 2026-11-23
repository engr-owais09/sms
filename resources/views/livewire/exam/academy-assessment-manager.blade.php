<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <h1 class="text-2xl font-bold text-slate-900">
            {{ __('sms.assessments.title') }}
        </h1>
        <p class="text-sm text-slate-500 mt-1">
            {{ __('sms.assessments.subtitle') }}
        </p>
    </div>

    @if($successMessage)
        <div class="bg-emerald-50 border border-emerald-300 text-emerald-900 px-4 py-3 rounded-lg text-sm">✓ {{ $successMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="bg-rose-50 border border-rose-300 text-rose-900 px-4 py-3 rounded-lg text-sm">✕ {{ $errorMessage }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Create Assessment Card -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-4">
            <h2 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-2">{{ __('sms.assessments.create_assessment') }}</h2>

            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.assessments.academy_batch') }}</label>
                <select wire:model.live="selectedBatchId" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                    <option value="">{{ __('sms.assessments.select_batch') }}</option>
                    @foreach($batches as $b)
                        <option value="{{ $b->id }}">{{ $b->name }} ({{ $b->course->name ?? '' }})</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.assessments.assessment_title') }}</label>
                <input type="text" wire:model="title" placeholder="{{ __('sms.assessments.title_placeholder') }}" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.assessments.type') }}</label>
                    <select wire:model="assessmentType" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                        <option value="chapter_test">{{ __('sms.assessments.chapter_test') }}</option>
                        <option value="mock_exam">{{ __('sms.assessments.mock_exam') }}</option>
                        <option value="past_paper">{{ __('sms.assessments.past_paper') }}</option>
                        <option value="final_test">{{ __('sms.assessments.final_test') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.assessments.date') }}</label>
                    <input type="date" wire:model="assessmentDate" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.assessments.max_marks') }}</label>
                    <input type="number" step="1" wire:model="maxMarks" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg font-mono">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.assessments.passing_marks') }}</label>
                    <input type="number" step="1" wire:model="passingMarks" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg font-mono">
                </div>
            </div>

            <button type="button" wire:click="createAssessment" class="w-full py-2.5 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg text-sm">
                {{ __('sms.assessments.create_btn') }}
            </button>
        </div>

        <!-- Score Entry & Ranked Results -->
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
                <div class="flex-1">
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.assessments.select_assessment') }}</label>
                    <select wire:model.live="selectedAssessmentId" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                        <option value="">{{ __('sms.assessments.choose_assessment') }}</option>
                        @foreach($assessments as $ass)
                            <option value="{{ $ass->id }}">{{ $ass->title }} ({{ $ass->assessment_date->format('Y-m-d') }} • {{ __('sms.assessments.col_marks') }}: {{ $ass->max_marks }}) [{{ strtoupper($ass->status) }}]</option>
                        @endforeach
                    </select>
                </div>

                @if($activeAssessment && count($scoreRows) > 0)
                    <div class="pt-4">
                        <button type="button" wire:click="saveScores(true)" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-lg text-xs">
                            ✓ {{ __('sms.assessments.save_publish') }}
                        </button>
                    </div>
                @endif
            </div>

            @if($activeAssessment)
                <div class="overflow-x-auto">
                    <table class="w-full text-start text-xs text-slate-600">
                        <thead class="bg-slate-50 uppercase font-semibold text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="py-3 px-3 text-start">{{ __('sms.assessments.col_rank') }}</th>
                                <th class="py-3 px-3 text-start">{{ __('sms.assessments.col_student') }}</th>
                                <th class="py-3 px-3 text-start">{{ __('sms.assessments.col_marks') }} ({{ $activeAssessment->max_marks }})</th>
                                <th class="py-3 px-3 text-start">{{ __('sms.assessments.col_absent') }}</th>
                                <th class="py-3 px-3 text-start">{{ __('sms.assessments.col_percentage') }}</th>
                                <th class="py-3 px-3 text-start">{{ __('sms.assessments.col_remarks') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($scoreRows as $idx => $row)
                                <tr>
                                    <td class="py-2.5 px-3 font-mono font-bold text-indigo-700">
                                        {{ $row['rank'] ? '#' . $row['rank'] : __('sms.common.none') }}
                                    </td>
                                    <td class="py-2.5 px-3 font-bold text-slate-900">
                                        {{ $row['full_name'] }}
                                        <span class="block text-[10px] font-mono text-slate-400">{{ $row['admission_number'] }}</span>
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <input type="number" step="0.5" wire:model="scoreRows.{{ $idx }}.marks" class="w-24 px-2 py-1 border border-slate-300 rounded font-mono text-xs">
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <input type="checkbox" wire:model="scoreRows.{{ $idx }}.is_absent" class="rounded text-rose-600">
                                    </td>
                                    <td class="py-2.5 px-3 font-mono font-semibold">
                                        {{ number_format($row['percentage'], 2) }}%
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <input type="text" wire:model="scoreRows.{{ $idx }}.remarks" placeholder="{{ __('sms.assessments.note_placeholder') }}" class="w-full px-2 py-1 border border-slate-300 rounded text-xs">
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="py-6 text-center text-slate-400">{{ __('sms.assessments.no_enrolled') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @else
                <div class="py-8 text-center text-slate-400 text-sm">{{ __('sms.assessments.empty_state') }}</div>
            @endif
        </div>
    </div>
</div>
