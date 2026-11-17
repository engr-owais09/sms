<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <h1 class="text-2xl font-bold text-slate-900">
            {{ $isRtl ? 'ٹائم ٹیبل بلڈر اور تصادم کی جانچ' : 'Timetable Builder & Clash Detection' }}
        </h1>
        <p class="text-sm text-slate-500 mt-1">
            {{ $isRtl ? 'سیکشن وار ہفتہ وار پیریڈز، اساتذہ اور کمروں کی تفویض' : 'Assign weekly periods with automatic teacher, room, and hybrid academy clash guards' }}
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
            <h2 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-2">{{ $isRtl ? 'نیا پیریڈ تفویض کریں' : 'Assign Timetable Slot' }}</h2>

            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Academic Session</label>
                <select wire:model.live="academicSessionId" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                    @foreach($sessions as $s)
                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Section</label>
                <select wire:model.live="schoolSectionId" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                    @foreach($sections as $sec)
                        <option value="{{ $sec->id }}">{{ $sec->schoolClass->name ?? '' }} - {{ $sec->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Day</label>
                    <select wire:model.live="dayOfWeek" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                        <option value="1">Monday</option>
                        <option value="2">Tuesday</option>
                        <option value="3">Wednesday</option>
                        <option value="4">Thursday</option>
                        <option value="5">Friday</option>
                        <option value="6">Saturday</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Period</label>
                    <select wire:model.live="schoolPeriodId" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                        @foreach($periods as $p)
                            <option value="{{ $p->id }}">{{ $p->label }} ({{ substr($p->start_time, 0, 5) }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Subject</label>
                <select wire:model.live="subjectId" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                    @foreach($subjects as $sub)
                        <option value="{{ $sub->id }}">{{ $sub->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Teacher</label>
                <select wire:model.live="teacherId" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                    <option value="">Unassigned</option>
                    @foreach($teachers as $t)
                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Room</label>
                <select wire:model.live="campusRoomId" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                    <option value="">Unassigned</option>
                    @foreach($rooms as $r)
                        <option value="{{ $r->id }}">{{ $r->room_code }} - {{ $r->name }}</option>
                    @endforeach
                </select>
            </div>

            <button type="button" wire:click="assignSlot" class="w-full py-2.5 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg text-sm">
                {{ $isRtl ? 'سلاٹ محفوظ کریں' : 'Assign Slot' }}
            </button>
        </div>

        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-4 border-b border-slate-200 bg-slate-50">
                <h2 class="font-bold text-slate-900 text-sm">{{ $isRtl ? 'ہفتہ وار ٹائم ٹیبل' : 'Weekly Timetable Entries' }}</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600">
                    <thead class="bg-slate-50 uppercase font-semibold text-slate-500 border-b border-slate-200">
                        <tr>
                            <th class="py-3 px-4">Day</th>
                            <th class="py-3 px-4">Period</th>
                            <th class="py-3 px-4">Subject</th>
                            <th class="py-3 px-4">Teacher</th>
                            <th class="py-3 px-4">Room</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($entries as $entry)
                            <tr class="hover:bg-slate-50">
                                <td class="py-2.5 px-4 font-semibold">{{ ['', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'][$entry->day_of_week] ?? $entry->day_of_week }}</td>
                                <td class="py-2.5 px-4">{{ $entry->period->label ?? '—' }}</td>
                                <td class="py-2.5 px-4 font-bold text-slate-900">{{ $entry->subject->name ?? '—' }}</td>
                                <td class="py-2.5 px-4">{{ $entry->teacher->name ?? '—' }}</td>
                                <td class="py-2.5 px-4 font-mono">{{ $entry->room->room_code ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-slate-400">No timetable slots assigned yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
