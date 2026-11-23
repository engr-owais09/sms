<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
    <!-- Header & Tabs -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">
                {{ __('sms.academic.title') }}
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                {{ __('sms.academic.subtitle') }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if($tenant?->isSchool())
                <button type="button" wire:click="$set('activeTab', 'sessions')" class="px-3.5 py-2 rounded-lg text-xs font-bold {{ $activeTab === 'sessions' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                    📅 {{ __('sms.academic.tab_sessions') }}
                </button>
                <button type="button" wire:click="$set('activeTab', 'classes')" class="px-3.5 py-2 rounded-lg text-xs font-bold {{ $activeTab === 'classes' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                    🏫 {{ __('sms.academic.tab_classes') }}
                </button>
                <button type="button" wire:click="$set('activeTab', 'subjects')" class="px-3.5 py-2 rounded-lg text-xs font-bold {{ $activeTab === 'subjects' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                    📚 {{ __('sms.academic.tab_subjects') }}
                </button>
                <button type="button" wire:click="$set('activeTab', 'schedule')" class="px-3.5 py-2 rounded-lg text-xs font-bold {{ $activeTab === 'schedule' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                    🔔 {{ __('sms.academic.tab_schedule') }}
                </button>
            @endif
            @if($tenant?->isAcademy())
                <button type="button" wire:click="$set('activeTab', 'academy')" class="px-3.5 py-2 rounded-lg text-xs font-bold {{ $activeTab === 'academy' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                    🎓 {{ __('sms.academic.tab_academy') }}
                </button>
            @endif
        </div>
    </div>

    @if($successMessage)
        <div class="bg-emerald-50 border border-emerald-300 text-emerald-900 px-4 py-3 rounded-lg text-sm">✓ {{ $successMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="bg-rose-50 border border-rose-300 text-rose-900 px-4 py-3 rounded-lg text-sm">✕ {{ $errorMessage }}</div>
    @endif

    <!-- TAB 1: ACADEMIC SESSIONS -->
    @if($activeTab === 'sessions')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-4">
                <h2 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-2">{{ __('sms.academic.create_session') }}</h2>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.academic.session_title') }}</label>
                    <input type="text" wire:model="sessionName" placeholder="{{ __('sms.academic.session_placeholder') }}" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                    @error('sessionName') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.academic.start_date') }}</label>
                        <input type="date" wire:model="sessionStartDate" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.academic.end_date') }}</label>
                        <input type="date" wire:model="sessionEndDate" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                    </div>
                </div>
                <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-700">
                    <input type="checkbox" wire:model="sessionIsCurrent" class="rounded text-indigo-600"> {{ __('sms.academic.set_current_session') }}
                </label>
                <button type="button" wire:click="saveSession" class="w-full py-2.5 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg text-sm">
                    {{ __('sms.academic.save_session') }}
                </button>
            </div>

            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-start text-xs text-slate-600">
                        <thead class="bg-slate-50 uppercase font-semibold text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="py-3 px-4 text-start">{{ __('sms.academic.col_session') }}</th>
                                <th class="py-3 px-4 text-start">{{ __('sms.academic.col_dates') }}</th>
                                <th class="py-3 px-4 text-start">{{ __('sms.common.status') }}</th>
                                <th class="py-3 px-4 text-center">{{ __('sms.common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($sessions as $s)
                                <tr>
                                    <td class="py-3 px-4 font-bold text-slate-900">{{ $s->name }}</td>
                                    <td class="py-3 px-4 font-mono">{{ $s->start_date->format('Y-m-d') }} → {{ $s->end_date->format('Y-m-d') }}</td>
                                    <td class="py-3 px-4">
                                        @if($s->is_current)
                                            <span class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 font-bold text-[10px]">{{ __('sms.common.current') }}</span>
                                        @else
                                            <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-600 font-bold text-[10px]">{{ __('sms.common.inactive_badge') }}</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <div class="inline-flex items-center gap-2">
                                            @if(!$s->is_current)
                                                <button type="button" wire:click="setCurrentSession('{{ $s->id }}')" class="text-indigo-600 font-bold hover:underline">{{ __('sms.academic.make_current') }}</button>
                                            @endif
                                            <button type="button" wire:click="deleteSession('{{ $s->id }}')" class="text-rose-600 font-bold hover:underline">{{ __('sms.common.delete') }}</button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-6 text-center text-slate-400">{{ __('sms.academic.no_sessions') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <!-- TAB 2: CLASSES & SECTIONS -->
    @if($activeTab === 'classes')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="space-y-6">
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-4">
                    <h2 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-2">{{ __('sms.academic.add_class') }}</h2>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.academic.class_title') }}</label>
                        <input type="text" wire:model="className" placeholder="{{ __('sms.academic.class_placeholder') }}" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                        @error('className') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.academic.numeric_level') }}</label>
                            <input type="number" wire:model="classNumericLevel" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.academic.display_order') }}</label>
                            <input type="number" wire:model="classDisplayOrder" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                        </div>
                    </div>
                    <button type="button" wire:click="saveClass" class="w-full py-2 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg text-sm">
                        {{ __('sms.academic.save_class') }}
                    </button>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-4">
                    <h2 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-2">{{ __('sms.academic.add_section') }}</h2>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.academic.parent_class') }}</label>
                        <select wire:model="sectionClassId" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                            <option value="">{{ __('sms.academic.select_class') }}</option>
                            @foreach($classes as $cls)
                                <option value="{{ $cls->id }}">{{ $cls->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.academic.section_name') }}</label>
                            <input type="text" wire:model="sectionName" placeholder="{{ __('sms.academic.section_placeholder') }}" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.academic.max_capacity') }}</label>
                            <input type="number" wire:model="sectionMaxCapacity" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.academic.class_teacher') }}</label>
                        <select wire:model="sectionTeacherId" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                            <option value="">{{ __('sms.common.unassigned') }}</option>
                            @foreach($teachers as $t)
                                <option value="{{ $t->id }}">{{ $t->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="button" wire:click="saveSection" class="w-full py-2 px-4 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-lg text-sm">
                        {{ __('sms.academic.save_section') }}
                    </button>
                </div>
            </div>

            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-4">
                <h2 class="text-base font-bold text-slate-900">{{ __('sms.academic.configured_classes') }}</h2>
                <div class="divide-y divide-slate-100">
                    @forelse($classes as $cls)
                        <div class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div>
                                <span class="font-bold text-slate-900 text-sm">{{ $cls->name }}</span>
                                <span class="text-xs text-slate-400 ms-2">({{ __('sms.academic.level') }} {{ $cls->numeric_level }})</span>
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                @forelse($cls->sections as $sec)
                                    <span class="px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-200 text-xs font-semibold">
                                        {{ $sec->name }} ({{ __('sms.academic.cap') }}: {{ $sec->max_capacity }})
                                    </span>
                                @empty
                                    <span class="text-xs text-slate-400">{{ __('sms.academic.no_sections') }}</span>
                                @endforelse
                            </div>
                        </div>
                    @empty
                        <div class="py-6 text-center text-slate-400 text-sm">{{ __('sms.academic.no_classes') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif

    <!-- TAB 3: SUBJECTS -->
    @if($activeTab === 'subjects')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-4">
                <h2 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-2">{{ __('sms.academic.create_subject') }}</h2>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.academic.subject_code') }}</label>
                    <input type="text" wire:model="subjectCode" placeholder="PHYS-10" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg font-mono uppercase">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.academic.subject_name') }}</label>
                    <input type="text" wire:model="subjectName" placeholder="Physics" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.academic.category') }}</label>
                    <select wire:model="subjectCategory" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                        <option value="general">{{ __('sms.academic.cat_general') }}</option>
                        <option value="science">{{ __('sms.academic.cat_science') }}</option>
                        <option value="arts">{{ __('sms.academic.cat_arts') }}</option>
                        <option value="languages">{{ __('sms.academic.cat_languages') }}</option>
                        <option value="technical">{{ __('sms.academic.cat_technical') }}</option>
                    </select>
                </div>
                <button type="button" wire:click="saveSubject" class="w-full py-2.5 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg text-sm">
                    {{ __('sms.academic.save_subject') }}
                </button>
            </div>

            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-start text-xs text-slate-600">
                        <thead class="bg-slate-50 uppercase font-semibold text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="py-3 px-4 text-start">{{ __('sms.academic.col_code') }}</th>
                                <th class="py-3 px-4 text-start">{{ __('sms.academic.col_name') }}</th>
                                <th class="py-3 px-4 text-start">{{ __('sms.academic.col_category') }}</th>
                                <th class="py-3 px-4 text-center">{{ __('sms.common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($subjects as $sub)
                                <tr>
                                    <td class="py-3 px-4 font-mono font-bold text-indigo-700">{{ $sub->code }}</td>
                                    <td class="py-3 px-4 font-bold text-slate-900">{{ $sub->name }}</td>
                                    <td class="py-3 px-4 uppercase">{{ $sub->category }}</td>
                                    <td class="py-3 px-4 text-center">
                                        <button type="button" wire:click="deleteSubject('{{ $sub->id }}')" class="text-rose-600 font-bold hover:underline">{{ __('sms.common.delete') }}</button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-6 text-center text-slate-400">{{ __('sms.academic.no_subjects') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <!-- TAB 4: BELL PERIODS & CAMPUS ROOMS -->
    @if($activeTab === 'schedule')
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-4">
                <h2 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-2">{{ __('sms.academic.bell_periods') }}</h2>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.academic.period_number') }}</label>
                        <input type="number" wire:model="periodNumber" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.academic.label') }}</label>
                        <input type="text" wire:model="periodLabel" placeholder="Period 1" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.academic.start_time') }}</label>
                        <input type="time" wire:model="periodStartTime" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.academic.end_time') }}</label>
                        <input type="time" wire:model="periodEndTime" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                    </div>
                </div>
                <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-700">
                    <input type="checkbox" wire:model="periodIsBreak" class="rounded text-indigo-600"> {{ __('sms.academic.break_period') }}
                </label>
                <button type="button" wire:click="savePeriod" class="w-full py-2 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg text-sm">
                    {{ __('sms.academic.save_period') }}
                </button>

                <div class="divide-y divide-slate-100 pt-3">
                    @foreach($periods as $p)
                        <div class="py-2 flex justify-between text-xs">
                            <span class="font-bold text-slate-800">#{{ $p->period_number }} — {{ $p->label }} @if($p->is_break) <span class="text-amber-600">{{ __('sms.academic.break_tag') }}</span> @endif</span>
                            <span class="font-mono text-slate-500">{{ substr($p->start_time, 0, 5) }} - {{ substr($p->end_time, 0, 5) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-4">
                <h2 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-2">{{ __('sms.academic.campus_rooms') }}</h2>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.academic.room_code') }}</label>
                        <input type="text" wire:model="roomCode" placeholder="R-102" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg font-mono uppercase">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.academic.room_title') }}</label>
                        <input type="text" wire:model="roomName" placeholder="Room 102" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.academic.type') }}</label>
                        <select wire:model="roomType" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                            <option value="classroom">{{ __('sms.academic.room_classroom') }}</option>
                            <option value="lab">{{ __('sms.academic.room_lab') }}</option>
                            <option value="hall">{{ __('sms.academic.room_hall') }}</option>
                            <option value="library">{{ __('sms.academic.room_library') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.academic.capacity') }}</label>
                        <input type="number" wire:model="roomCapacity" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                    </div>
                </div>
                <button type="button" wire:click="saveRoom" class="w-full py-2 px-4 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-lg text-sm">
                    {{ __('sms.academic.save_room') }}
                </button>

                <div class="divide-y divide-slate-100 pt-3">
                    @foreach($rooms as $r)
                        <div class="py-2 flex justify-between text-xs">
                            <span class="font-bold text-slate-800">{{ $r->room_code }} — {{ $r->name }} ({{ strtoupper($r->room_type) }})</span>
                            <span class="font-mono text-slate-500">{{ __('sms.academic.cap') }}: {{ $r->capacity }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <!-- TAB 5: ACADEMY PROGRAMS, COURSES & BATCHES -->
    @if($activeTab === 'academy')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="space-y-6">
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-3">
                    <h2 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2">{{ __('sms.academic.create_program') }}</h2>
                    <input type="text" wire:model="programName" placeholder="{{ __('sms.academic.program_placeholder') }}" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg">
                    <select wire:model="programCategory" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg">
                        <option value="entry_test">{{ __('sms.academic.prog_entry_test') }}</option>
                        <option value="academic_coaching">{{ __('sms.academic.prog_coaching') }}</option>
                        <option value="skill_development">{{ __('sms.academic.prog_skill') }}</option>
                    </select>
                    <button type="button" wire:click="saveAcademyProgram" class="w-full py-2 bg-slate-900 text-white text-xs font-bold rounded-lg">{{ __('sms.academic.save_program') }}</button>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-3">
                    <h2 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2">{{ __('sms.academic.create_course') }}</h2>
                    <select wire:model="courseProgramId" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg">
                        <option value="">{{ __('sms.academic.select_program') }}</option>
                        @foreach($programs as $p)
                            <option value="{{ $p->id }}">{{ $p->name }}</option>
                        @endforeach
                    </select>
                    <input type="text" wire:model="courseName" placeholder="{{ __('sms.academic.course_placeholder') }}" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg">
                    <div class="grid grid-cols-2 gap-2">
                        <input type="number" wire:model="courseBaseFee" placeholder="{{ __('sms.academic.fee_pkr') }}" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg">
                        <input type="number" wire:model="courseDurationWeeks" placeholder="{{ __('sms.academic.weeks') }}" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg">
                    </div>
                    <button type="button" wire:click="saveAcademyCourse" class="w-full py-2 bg-indigo-600 text-white text-xs font-bold rounded-lg">{{ __('sms.academic.save_course') }}</button>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-3">
                    <h2 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2">{{ __('sms.academic.create_batch') }}</h2>
                    <select wire:model="batchCourseId" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg">
                        <option value="">{{ __('sms.academic.select_course') }}</option>
                        @foreach($courses as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                    <input type="text" wire:model="batchName" placeholder="{{ __('sms.academic.batch_placeholder') }}" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg">
                    <div class="grid grid-cols-2 gap-2">
                        <input type="date" wire:model="batchStartDate" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg">
                        <input type="date" wire:model="batchEndDate" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="number" wire:model="batchMaxCapacity" placeholder="{{ __('sms.academic.capacity') }}" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg">
                        <select wire:model="batchStatus" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg">
                            <option value="upcoming">{{ __('sms.academic.status_upcoming') }}</option>
                            <option value="in_progress">{{ __('sms.academic.status_in_progress') }}</option>
                            <option value="completed">{{ __('sms.academic.status_completed') }}</option>
                        </select>
                    </div>
                    <select wire:model="batchInstructorId" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg">
                        <option value="">{{ __('sms.academic.optional_instructor') }}</option>
                        @foreach($teachers as $t)
                            <option value="{{ $t->id }}">{{ $t->name }}</option>
                        @endforeach
                    </select>
                    <button type="button" wire:click="saveAcademyBatch" class="w-full py-2 bg-emerald-600 text-white text-xs font-bold rounded-lg">{{ __('sms.academic.save_batch') }}</button>
                </div>
            </div>

            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-start text-xs text-slate-600">
                        <thead class="bg-slate-50 uppercase font-semibold text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="py-3 px-4 text-start">{{ __('sms.academic.col_batch') }}</th>
                                <th class="py-3 px-4 text-start">{{ __('sms.academic.col_course') }}</th>
                                <th class="py-3 px-4 text-start">{{ __('sms.academic.col_dates') }}</th>
                                <th class="py-3 px-4 text-start">{{ __('sms.academic.col_seats') }}</th>
                                <th class="py-3 px-4 text-start">{{ __('sms.common.status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($batches as $b)
                                <tr>
                                    <td class="py-3 px-4 font-bold text-slate-900">{{ $b->name }}</td>
                                    <td class="py-3 px-4">{{ $b->course->name ?? __('sms.common.none') }}</td>
                                    <td class="py-3 px-4 font-mono">{{ $b->start_date->format('Y-m-d') }} → {{ $b->end_date->format('Y-m-d') }}</td>
                                    <td class="py-3 px-4 font-mono">{{ $b->availableSeats() }} / {{ $b->max_capacity }}</td>
                                    <td class="py-3 px-4 uppercase font-bold">{{ $b->status }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-6 text-center text-slate-400">{{ __('sms.academic.no_batches') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
