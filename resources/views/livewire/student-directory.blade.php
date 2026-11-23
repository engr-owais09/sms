<div class="space-y-6">
    <!-- Header Controls -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">
                {{ __('sms.students.title') }}
            </h1>
            <p class="text-sm text-slate-500">
                {{ __('sms.students.subtitle') }}
            </p>
        </div>

        <a href="{{ route('tenant.students.create') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-indigo-600 text-white font-bold text-sm shadow hover:bg-indigo-700 transition-all">
            <span>+</span>
            <span>{{ __('sms.students.new_admission') }}</span>
        </a>
    </div>

    @if($successMessage)
        <div class="bg-emerald-50 border border-emerald-300 text-emerald-900 px-4 py-3 rounded-lg text-sm">✓ {{ $successMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="bg-rose-50 border border-rose-300 text-rose-900 px-4 py-3 rounded-lg text-sm">✕ {{ $errorMessage }}</div>
    @endif

    @if($editingStudentId)
        <div class="bg-white p-5 rounded-xl border-2 border-indigo-200 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                <h2 class="text-sm font-bold uppercase text-indigo-700">{{ __('sms.students.edit_profile') }}</h2>
                <button type="button" wire:click="$set('editingStudentId', null)" class="text-xs text-slate-400 hover:text-slate-700">✕ {{ __('sms.common.close') }}</button>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                <input type="text" wire:model="editFirstName" placeholder="{{ __('sms.students.first_name') }}" class="px-3 py-2 text-xs border border-slate-300 rounded-lg">
                <input type="text" wire:model="editLastName" placeholder="{{ __('sms.students.last_name') }}" class="px-3 py-2 text-xs border border-slate-300 rounded-lg">
                <input type="date" wire:model="editDateOfBirth" class="px-3 py-2 text-xs border border-slate-300 rounded-lg">
                <select wire:model="editStatus" class="px-3 py-2 text-xs border border-slate-300 rounded-lg">
                    <option value="active">{{ __('sms.common.active') }}</option>
                    <option value="inquiry">{{ __('sms.students.status_inquiry') }}</option>
                    <option value="alumni">{{ __('sms.students.status_alumni') }}</option>
                    <option value="transferred">{{ __('sms.students.status_transferred') }}</option>
                    <option value="dropped">{{ __('sms.students.status_dropped') }}</option>
                </select>
                <input type="text" wire:model="editBForm" placeholder="{{ __('sms.students.b_form') }}" class="px-3 py-2 text-xs border border-slate-300 rounded-lg">
                <input type="text" wire:model="editGuardianName" placeholder="{{ __('sms.students.guardian_name') }}" class="px-3 py-2 text-xs border border-slate-300 rounded-lg">
                <input type="text" wire:model="editGuardianPhone" placeholder="{{ __('sms.students.guardian_phone') }}" class="px-3 py-2 text-xs border border-slate-300 rounded-lg">
                <button type="button" wire:click="saveStudentEdit" class="px-4 py-2 bg-indigo-600 text-white text-xs font-bold rounded-lg">{{ __('sms.common.save_changes') }}</button>
            </div>
        </div>
    @endif

    @if($promotingStudentId)
        <div class="bg-white p-5 rounded-xl border-2 border-emerald-200 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                <h2 class="text-sm font-bold uppercase text-emerald-700">{{ __('sms.students.promote_title') }}</h2>
                <button type="button" wire:click="$set('promotingStudentId', null)" class="text-xs text-slate-400 hover:text-slate-700">✕ {{ __('sms.common.close') }}</button>
            </div>
            @if($tenant?->isSchool())
                <div class="grid grid-cols-1 sm:grid-cols-5 gap-3">
                    <select wire:model="toSessionId" class="px-3 py-2 text-xs border border-slate-300 rounded-lg">
                        <option value="">{{ __('sms.students.target_session') }}</option>
                        @foreach($sessions as $sess)
                            <option value="{{ $sess->id }}">{{ $sess->name }}</option>
                        @endforeach
                    </select>
                    <select wire:model="toSectionId" class="px-3 py-2 text-xs border border-slate-300 rounded-lg">
                        <option value="">{{ __('sms.students.target_section') }}</option>
                        @foreach($sections as $sec)
                            <option value="{{ $sec->id }}">{{ $sec->schoolClass->name ?? '' }} - {{ $sec->name }}</option>
                        @endforeach
                    </select>
                    <select wire:model="promotionResult" class="px-3 py-2 text-xs border border-slate-300 rounded-lg">
                        <option value="promoted">{{ __('sms.students.promoted') }}</option>
                        <option value="retained">{{ __('sms.students.retained') }}</option>
                        <option value="graduated">{{ __('sms.students.graduated') }}</option>
                    </select>
                    <input type="text" wire:model="promotionRemarks" placeholder="{{ __('sms.common.remarks') }}" class="px-3 py-2 text-xs border border-slate-300 rounded-lg">
                    <button type="button" wire:click="promoteStudent" class="px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-lg">{{ __('sms.students.confirm_promotion') }}</button>
                </div>
            @endif
            @if($tenant?->isAcademy())
                <div class="flex flex-col sm:flex-row gap-3">
                    <select wire:model="targetBatchId" class="flex-1 px-3 py-2 text-xs border border-slate-300 rounded-lg">
                        <option value="">{{ __('sms.students.enroll_batch_placeholder') }}</option>
                        @foreach($batches as $b)
                            <option value="{{ $b->id }}">{{ $b->name }} ({{ $b->course->name ?? '' }})</option>
                        @endforeach
                    </select>
                    <button type="button" wire:click="promoteStudent" class="px-4 py-2 bg-purple-600 text-white text-xs font-bold rounded-lg">{{ __('sms.students.enroll_batch') }}</button>
                </div>
            @endif
        </div>
    @endif

    @if($documentStudentId)
        <div class="bg-white p-5 rounded-xl border-2 border-amber-200 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                <h2 class="text-sm font-bold uppercase text-amber-800">{{ __('sms.students.document_vault') }}</h2>
                <button type="button" wire:click="$set('documentStudentId', null)" class="text-xs text-slate-400 hover:text-slate-700">✕ {{ __('sms.common.close') }}</button>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                <select wire:model="documentType" class="px-3 py-2 text-xs border border-slate-300 rounded-lg">
                    <option value="b_form">{{ __('sms.students.doc_b_form') }}</option>
                    <option value="birth_certificate">{{ __('sms.students.doc_birth_cert') }}</option>
                    <option value="leaving_certificate">{{ __('sms.students.doc_leaving_cert') }}</option>
                    <option value="medical_form">{{ __('sms.students.doc_medical') }}</option>
                    <option value="passport_photo">{{ __('sms.students.doc_photo') }}</option>
                </select>
                <input type="text" wire:model="documentFilename" placeholder="{{ __('sms.students.doc_filename_placeholder') }}" class="px-3 py-2 text-xs border border-slate-300 rounded-lg">
                <input type="file" wire:model="uploadedDocument" class="px-3 py-1.5 text-xs border border-slate-300 rounded-lg">
                <button type="button" wire:click="addStudentDocument" class="px-4 py-2 bg-amber-600 text-white text-xs font-bold rounded-lg">{{ __('sms.students.add_document') }}</button>
            </div>
            <div class="divide-y divide-slate-100 text-xs">
                @forelse($activeStudentDocuments as $doc)
                    <div class="py-2 flex items-center justify-between gap-2">
                        <div>
                            <span class="font-bold uppercase text-slate-800">{{ $doc->document_type }}</span> —
                            <span class="font-mono text-slate-600">{{ $doc->original_filename }}</span>
                            @if($doc->verified_at)
                                <span class="ms-2 px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 font-bold text-[10px]">{{ __('sms.common.verified') }}</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            @if(!$doc->verified_at)
                                <button type="button" wire:click="verifyStudentDocument('{{ $doc->id }}')" class="text-emerald-600 font-bold hover:underline">{{ __('sms.common.verify') }}</button>
                            @endif
                            <button type="button" wire:click="deleteStudentDocument('{{ $doc->id }}')" class="text-rose-600 font-bold hover:underline">{{ __('sms.common.delete') }}</button>
                        </div>
                    </div>
                @empty
                    <div class="py-2 text-slate-400">{{ __('sms.students.no_documents') }}</div>
                @endforelse
            </div>
        </div>
    @endif

    <!-- Search & Filter Bar -->
    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col sm:flex-row gap-4">
        <div class="flex-1">
            <input type="text" wire:model.live.debounce.300ms="search" class="w-full px-4 py-2 border border-slate-300 rounded-lg text-sm" placeholder="{{ __('sms.students.search_placeholder') }}">
        </div>

        <div class="w-full sm:w-48">
            <select wire:model.live="statusFilter" class="w-full px-3.5 py-2 border border-slate-300 rounded-lg text-sm">
                <option value="">{{ __('sms.students.all_statuses') }}</option>
                <option value="active">{{ __('sms.common.active') }}</option>
                <option value="inquiry">{{ __('sms.students.status_inquiry') }}</option>
                <option value="alumni">{{ __('sms.students.status_alumni') }}</option>
                <option value="transferred">{{ __('sms.students.status_transferred') }}</option>
            </select>
        </div>
    </div>

    <!-- Students Table -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-start text-sm">
                <thead class="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-3.5 text-start">{{ __('sms.students.col_admission_no') }}</th>
                        <th class="px-6 py-3.5 text-start">{{ __('sms.students.col_student_name') }}</th>
                        <th class="px-6 py-3.5 text-start">{{ __('sms.students.col_enrollment') }}</th>
                        <th class="px-6 py-3.5 text-start">{{ __('sms.students.col_guardian') }}</th>
                        <th class="px-6 py-3.5 text-start">{{ __('sms.common.status') }}</th>
                        <th class="px-6 py-3.5 text-center">{{ __('sms.common.actions') }}</th>
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
                                <div class="text-xs text-slate-400">{{ __('sms.students.dob') }}: {{ $st->date_of_birth->format('M d, Y') }} &bull; {{ ucfirst($st->gender) }}</div>
                            </td>
                            <td class="px-6 py-4">
                                @if($tenant?->isSchool())
                                    @php $currentEnr = $st->schoolEnrollments->first(); @endphp
                                    @if($currentEnr && $currentEnr->schoolSection)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                            {{ $currentEnr->schoolSection->schoolClass->name }} - {{ $currentEnr->schoolSection->name }}
                                        </span>
                                    @else
                                        <span class="text-xs text-slate-400">{{ __('sms.common.unassigned') }}</span>
                                    @endif
                                @else
                                    <div class="flex flex-wrap gap-1">
                                        @forelse($st->academyEnrollments as $enr)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-50 text-purple-700 border border-purple-200">
                                                {{ $enr->batch->name }}
                                            </span>
                                        @empty
                                            <span class="text-xs text-slate-400">{{ __('sms.students.no_active_batches') }}</span>
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
                                    <span class="text-xs text-slate-400">{{ __('sms.students.na') }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $st->status === 'active' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-600' }}">
                                    {{ ucfirst($st->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center text-xs font-bold">
                                <div class="inline-flex items-center gap-2">
                                    <button type="button" wire:click="openEditModal('{{ $st->id }}')" class="text-indigo-600 hover:underline">{{ __('sms.common.edit') }}</button>
                                    <button type="button" wire:click="openPromotionModal('{{ $st->id }}')" class="text-emerald-600 hover:underline">{{ __('sms.students.promote') }}</button>
                                    <button type="button" wire:click="openDocumentsModal('{{ $st->id }}')" class="text-amber-600 hover:underline">{{ __('sms.students.docs') }} ({{ $st->documents->count() }})</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-10 text-center text-slate-500">
                                {{ __('sms.students.no_students') }}
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
