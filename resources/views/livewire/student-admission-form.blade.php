<div class="max-w-4xl mx-auto bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
    <!-- Header -->
    <div class="bg-slate-900 text-white p-6 sm:p-8 flex items-center justify-between">
        <div>
            <span class="text-xs font-semibold uppercase tracking-wider text-indigo-400">
                {{ $tenant?->isSchool() ? 'Formal School Admissions' : 'Academy Rolling Enrollment' }}
            </span>
            <h2 class="text-2xl font-bold mt-1">
                {{ $isRtl ? 'طلبہ کا باقاعدہ داخلہ فارم' : 'New Student Admission & Registration' }}
            </h2>
        </div>
        <span class="text-3xl">{{ $tenant?->isSchool() ? '🏫' : '🎓' }}</span>
    </div>

    <!-- Feedback Banner -->
    @if($successMessage)
        <div class="m-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-semibold flex items-center justify-between">
            <span>✓ {{ $successMessage }}</span>
            <button type="button" wire:click="$set('successMessage', null)" class="text-emerald-600 hover:text-emerald-900">&times;</button>
        </div>
    @endif

    <form wire:submit="submit" class="p-6 sm:p-8 space-y-8">
        <!-- SECTION 1: Student Biographical Details -->
        <div>
            <h3 class="text-base font-bold text-slate-900 border-b border-slate-200 pb-2 mb-4">
                {{ $isRtl ? '1. طالب علم کی ذاتی معلومات' : '1. Student Personal Information' }}
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">{{ $isRtl ? 'پہلا نام' : 'First Name' }} *</label>
                    <input type="text" wire:model="firstName" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500" placeholder="Muhammad">
                    @error('firstName') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">{{ $isRtl ? 'آخری نام' : 'Last Name' }} *</label>
                    <input type="text" wire:model="lastName" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500" placeholder="Ahmed">
                    @error('lastName') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">{{ $isRtl ? 'جنس' : 'Gender' }} *</label>
                    <select wire:model="gender" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg">
                        <option value="male">Male (مرد)</option>
                        <option value="female">Female (خاتون)</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">{{ $isRtl ? 'تاریخ پیدائش' : 'Date of Birth' }} *</label>
                    <input type="date" wire:model="dateOfBirth" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg">
                    @error('dateOfBirth') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">{{ $isRtl ? 'بے فارم / شناختی نمبر' : 'B-Form / CRC Number' }}</label>
                    <input type="text" wire:model="bForm" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg" placeholder="42101-1234567-1">
                    <span class="text-[11px] text-slate-400 block mt-0.5">🔒 AES-256 Encrypted with Blind Index</span>
                    @error('bForm') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        <!-- SECTION 2: Academic Assignment -->
        @if($tenant?->isSchool())
            <div>
                <h3 class="text-base font-bold text-slate-900 border-b border-slate-200 pb-2 mb-4">
                    {{ $isRtl ? '2. اسکول کلاس اور سیکشن کی تفویض' : '2. Class & Section Allocation' }}
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">{{ $isRtl ? 'تعلیمی سیشن' : 'Academic Session' }} *</label>
                        <select wire:model="sessionId" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg">
                            <option value="">-- Select Session --</option>
                            @foreach($sessions as $sess)
                                <option value="{{ $sess->id }}">{{ $sess->name }}</option>
                            @endforeach
                        </select>
                        @error('sessionId') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">{{ $isRtl ? 'کلاس' : 'Class / Grade' }} *</label>
                        <select wire:model.live="classId" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg">
                            <option value="">-- Select Class --</option>
                            @foreach($classes as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                        @error('classId') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">{{ $isRtl ? 'سیکشن' : 'Section' }} *</label>
                        <select wire:model="sectionId" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg">
                            <option value="">-- Select Section --</option>
                            @foreach($sections as $sec)
                                <option value="{{ $sec->id }}">{{ $sec->name }} (Cap: {{ $sec->max_capacity }})</option>
                            @endforeach
                        </select>
                        @error('sectionId') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>
        @else
            <div>
                <h3 class="text-base font-bold text-slate-900 border-b border-slate-200 pb-2 mb-4">
                    {{ $isRtl ? '2. اکیڈمی کورسز اور بیجز کا انتخاب' : '2. Academy Courses & Batch Enrollment' }}
                </h3>
                <p class="text-xs text-slate-500 mb-3">
                    {{ $isRtl ? 'طلبہ بیک وقت متعدد بیجز میں داخلہ لے سکتے ہیں۔' : 'Students can enroll in multiple simultaneous course batches.' }}
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @forelse($batches as $b)
                        <label class="p-3.5 rounded-xl border border-slate-200 bg-slate-50 flex items-center justify-between cursor-pointer hover:border-indigo-400">
                            <div>
                                <input type="checkbox" wire:model="selectedBatchIds" value="{{ $b->id }}" class="rounded text-indigo-600 focus:ring-indigo-500 me-2">
                                <span class="font-bold text-sm text-slate-800">{{ $b->name }}</span>
                                <div class="text-xs text-slate-500">{{ $b->course->name }} &bull; Seats left: {{ $b->availableSeats() }}</div>
                            </div>
                            <span class="text-xs font-semibold px-2 py-0.5 rounded bg-indigo-100 text-indigo-800">
                                PKR {{ number_format($b->course->base_fee) }}
                            </span>
                        </label>
                    @empty
                        <div class="text-sm text-slate-500 col-span-2">No active batches created yet.</div>
                    @endforelse
                </div>
                @error('selectedBatchIds') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
        @endif

        <!-- SECTION 3: Guardian Details -->
        <div>
            <h3 class="text-base font-bold text-slate-900 border-b border-slate-200 pb-2 mb-4">
                {{ $isRtl ? '3. والدین / سرپرست کی معلومات' : '3. Guardian & Billing Contact' }}
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">{{ $isRtl ? 'سرپرست کا نام' : 'Guardian Full Name' }} *</label>
                    <input type="text" wire:model="guardianName" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg" placeholder="Tariq Ahmed">
                    @error('guardianName') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">{{ $isRtl ? 'موبائل نمبر (SMS/WhatsApp)' : 'Mobile Phone' }} *</label>
                    <input type="text" wire:model="guardianPhone" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg" placeholder="03001234567">
                    @error('guardianPhone') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">{{ $isRtl ? 'رشتہ' : 'Relationship' }} *</label>
                    <select wire:model="guardianRelation" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg">
                        <option value="father">Father (والد)</option>
                        <option value="mother">Mother (والدہ)</option>
                        <option value="legal_guardian">Legal Guardian (قانونی سرپرست)</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="pt-4 border-t border-slate-200 flex justify-end">
            <button type="submit" class="px-8 py-3 rounded-xl bg-indigo-600 text-white font-bold text-sm shadow hover:bg-indigo-700 transition-all">
                {{ $isRtl ? 'داخلہ مکمل کریں ✓' : 'Complete Admission & Enroll →' }}
            </button>
        </div>
    </form>
</div>

