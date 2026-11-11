<div class="space-y-6">
    <!-- Header Controls -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">
                {{ $isRtl ? 'طلبہ کی ڈائریکٹری' : 'Student Directory' }}
            </h1>
            <p class="text-sm text-slate-500">
                {{ $isRtl ? 'تمام رجسٹرڈ طلبہ، کلاسز اور والدین کا ریکارڈ۔' : 'Master student directory with encrypted PII and enrollment records.' }}
            </p>
        </div>

        <a href="{{ route('tenant.students.create') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-indigo-600 text-white font-bold text-sm shadow hover:bg-indigo-700 transition-all">
            <span>+</span>
            <span>{{ $isRtl ? 'نیا داخلہ کریں' : 'New Admission' }}</span>
        </a>
    </div>

    <!-- Search & Filter Bar -->
    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col sm:flex-row gap-4">
        <div class="flex-1">
            <input type="text" wire:model.live.debounce.300ms="search" class="w-full px-4 py-2 border border-slate-300 rounded-lg text-sm" placeholder="{{ $isRtl ? 'نام یا داخلہ نمبر سے تلاش کریں...' : 'Search by student name or admission number...' }}">
        </div>

        <div class="w-full sm:w-48">
            <select wire:model.live="statusFilter" class="w-full px-3.5 py-2 border border-slate-300 rounded-lg text-sm">
                <option value="">All Statuses</option>
                <option value="active">Active</option>
                <option value="inquiry">Inquiry</option>
                <option value="alumni">Alumni</option>
                <option value="transferred">Transferred</option>
            </select>
        </div>
    </div>

    <!-- Students Table -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-start text-sm">
                <thead class="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-3.5 text-start">{{ $isRtl ? 'داخلہ نمبر' : 'Admission No.' }}</th>
                        <th class="px-6 py-3.5 text-start">{{ $isRtl ? 'طالب علم کا نام' : 'Student Name' }}</th>
                        <th class="px-6 py-3.5 text-start">{{ $isRtl ? 'کلاس / بیچ' : 'Enrollment / Cohort' }}</th>
                        <th class="px-6 py-3.5 text-start">{{ $isRtl ? 'سرپرست اور فون' : 'Guardian & Contact' }}</th>
                        <th class="px-6 py-3.5 text-start">{{ $isRtl ? 'حالت' : 'Status' }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($students as $st)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="px-6 py-4 font-mono font-bold text-xs text-indigo-600">
                                {{ $st->admission_number }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-900">{{ $st->full_name }}</div>
                                <div class="text-xs text-slate-400">DOB: {{ $st->date_of_birth->format('M d, Y') }} &bull; {{ ucfirst($st->gender) }}</div>
                            </td>
                            <td class="px-6 py-4">
                                @if($tenant?->isSchool())
                                    @php $currentEnr = $st->schoolEnrollments->first(); @endphp
                                    @if($currentEnr && $currentEnr->schoolSection)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                            {{ $currentEnr->schoolSection->schoolClass->name }} - {{ $currentEnr->schoolSection->name }}
                                        </span>
                                    @else
                                        <span class="text-xs text-slate-400">Unassigned</span>
                                    @endif
                                @else
                                    <div class="flex flex-wrap gap-1">
                                        @forelse($st->academyEnrollments as $enr)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-50 text-purple-700 border border-purple-200">
                                                {{ $enr->batch->name }}
                                            </span>
                                        @empty
                                            <span class="text-xs text-slate-400">No active batches</span>
                                        @endforelse
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @php $guard = $st->guardians->first(); @endphp
                                @if($guard)
                                    <div class="text-xs font-medium text-slate-800">{{ $guard->full_name }}</div>
                                    <div class="text-xs text-slate-500 font-mono">{{ $guard->phone }}</div>
                                @else
                                    <span class="text-xs text-slate-400">N/A</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $st->status === 'active' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-600' }}">
                                    {{ ucfirst($st->status) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-10 text-center text-slate-500">
                                {{ $isRtl ? 'کوئی طالب علم نہیں ملا۔' : 'No student records found.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 border-t border-slate-200">
            {{ $students->links() }}
        </div>
    </div>
</div>

