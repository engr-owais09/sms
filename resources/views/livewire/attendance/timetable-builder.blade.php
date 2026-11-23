<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <h1 class="text-2xl font-bold text-slate-900">
            {{ __('sms.timetable.title') }}
        </h1>
        <p class="text-sm text-slate-500 mt-1">
            {{ __('sms.timetable.subtitle') }}
        </p>
    </div>

    @if($successMessage)
        <div class="bg-emerald-50 border border-emerald-300 text-emerald-900 px-4 py-3 rounded-lg text-sm">✓ {{ $successMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="bg-rose-50 border border-rose-300 text-rose-900 px-4 py-3 rounded-lg text-sm">✕ {{ $errorMessage }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-4">
            <h2 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-2">{{ __('sms.timetable.assign_slot_heading') }}</h2>

            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.timetable.academic_session') }}</label>
                <select wire:model.live="academicSessionId" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                    @foreach($sessions as $s)
                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.timetable.section') }}</label>
                <select wire:model.live="schoolSectionId" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                    @foreach($sections as $sec)
                        <option value="{{ $sec->id }}">{{ $sec->schoolClass->name ?? '' }} - {{ $sec->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.timetable.day') }}</label>
                    <select wire:model.live="dayOfWeek" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                        @foreach([1, 2, 3, 4, 5, 6] as $d)
                            <option value="{{ $d }}">{{ __('sms.timetable.days.' . $d) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.timetable.period') }}</label>
                    <select wire:model.live="schoolPeriodId" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                        @foreach($periods as $p)
                            <option value="{{ $p->id }}">{{ $p->label }} ({{ substr($p->start_time, 0, 5) }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.timetable.subject') }}</label>
                <select wire:model.live="subjectId" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                    @foreach($subjects as $sub)
                        <option value="{{ $sub->id }}">{{ $sub->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.timetable.teacher') }}</label>
                <select wire:model.live="teacherId" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                    <option value="">{{ __('sms.common.unassigned') }}</option>
                    @foreach($teachers as $t)
                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.timetable.room') }}</label>
                <select wire:model.live="campusRoomId" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                    <option value="">{{ __('sms.common.unassigned') }}</option>
                    @foreach($rooms as $r)
                        <option value="{{ $r->id }}">{{ $r->room_code }} - {{ $r->name }}</option>
                    @endforeach
                </select>
            </div>

            <button type="button" wire:click="assignSlot" class="w-full py-2.5 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg text-sm">
                {{ __('sms.timetable.assign_slot') }}
            </button>
        </div>

        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-4 border-b border-slate-200 bg-slate-50">
                <h2 class="font-bold text-slate-900 text-sm">{{ __('sms.timetable.weekly_entries') }}</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-start text-xs text-slate-600">
                    <thead class="bg-slate-50 uppercase font-semibold text-slate-500 border-b border-slate-200">
                        <tr>
                            <th class="py-3 px-4 text-start">{{ __('sms.timetable.day') }}</th>
                            <th class="py-3 px-4 text-start">{{ __('sms.timetable.period') }}</th>
                            <th class="py-3 px-4 text-start">{{ __('sms.timetable.subject') }}</th>
                            <th class="py-3 px-4 text-start">{{ __('sms.timetable.teacher') }}</th>
                            <th class="py-3 px-4 text-start">{{ __('sms.timetable.room') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($entries as $entry)
                            <tr class="hover:bg-slate-50">
                                <td class="py-2.5 px-4 font-semibold">{{ __('sms.timetable.days.' . $entry->day_of_week) }}</td>
                                <td class="py-2.5 px-4">{{ $entry->period->label ?? __('sms.common.none') }}</td>
                                <td class="py-2.5 px-4 font-bold text-slate-900">{{ $entry->subject->name ?? __('sms.common.none') }}</td>
                                <td class="py-2.5 px-4">{{ $entry->teacher->name ?? __('sms.common.none') }}</td>
                                <td class="py-2.5 px-4 font-mono">{{ $entry->room->room_code ?? __('sms.common.none') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-slate-400">{{ __('sms.timetable.no_entries') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Teacher Substitution / Proxy Assignment Section -->
            <div class="p-4 border-t border-slate-200 bg-slate-50/60 space-y-3">
                <h3 class="font-bold text-slate-900 text-xs uppercase tracking-wider">
                    {{ __('sms.timetable.substitution_heading') }}
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                    <select wire:model="substitutionEntryId" class="px-3 py-2 text-xs border border-slate-300 rounded-lg bg-white">
                        <option value="">{{ __('sms.timetable.select_slot') }}</option>
                        @foreach($entries as $entry)
                            <option value="{{ $entry->id }}">
                                {{ __('sms.timetable.days.' . $entry->day_of_week) }} - {{ $entry->period->label ?? '' }} ({{ $entry->subject->name ?? '' }})
                            </option>
                        @endforeach
                    </select>
                    <input type="date" wire:model.live="substitutionDate" class="px-3 py-2 text-xs border border-slate-300 rounded-lg bg-white">
                    <select wire:model="substituteTeacherId" class="px-3 py-2 text-xs border border-slate-300 rounded-lg bg-white">
                        <option value="">{{ __('sms.timetable.select_substitute') }}</option>
                        @foreach($teachers as $t)
                            <option value="{{ $t->id }}">{{ $t->name }}</option>
                        @endforeach
                    </select>
                    <button type="button" wire:click="assignSubstitute" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg">
                        {{ __('sms.timetable.assign_proxy') }}
                    </button>
                </div>

                @if(isset($substitutions) && count($substitutions) > 0)
                    <div class="pt-2 space-y-1">
                        @foreach($substitutions as $subSession)
                            <div class="text-xs flex items-center justify-between bg-white px-3 py-1.5 rounded border border-emerald-200">
                                <span>
                                    <strong>{{ $subSession->timetableEntry->period->label ?? __('sms.timetable.period') }}</strong>
                                    ({{ $subSession->timetableEntry->subject->name ?? __('sms.timetable.subject') }})
                                </span>
                                <span class="text-emerald-700 font-bold">
                                    {{ __('sms.timetable.proxy') }}: {{ $subSession->markedBy->name ?? '' }} ({{ $subSession->attendance_date->format('Y-m-d') }})
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
