<div class="max-w-3xl mx-auto bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
    <!-- Progress Indicator Header -->
    <div class="bg-slate-900 text-white p-6 sm:p-8">
        <div class="flex items-center justify-between mb-4">
            <span class="text-xs font-semibold uppercase tracking-wider text-indigo-400">
                {{ $isRtl ? 'آن بورڈنگ وزرڈ' : 'Institute Setup Wizard' }}
            </span>
            <span class="text-sm font-medium text-slate-300">
                {{ $isRtl ? "مرحلہ {$currentStep} از {$totalSteps}" : "Step {$currentStep} of {$totalSteps}" }}
            </span>
        </div>
        
        <!-- Progress Bar -->
        <div class="w-full bg-slate-800 rounded-full h-2">
            <div class="bg-indigo-500 h-2 rounded-full transition-all duration-300" style="width: {{ ($currentStep / $totalSteps) * 100 }}%"></div>
        </div>
    </div>

    <div class="p-6 sm:p-8">
        <!-- STEP 1: Institute Type Selection -->
        @if($currentStep === 1)
            <div>
                <h2 class="text-2xl font-bold text-slate-900 mb-2">
                    {{ $isRtl ? 'اپنے تعلیمی ادارے کی قسم منتخب کریں' : 'Select your Institute Type' }}
                </h2>
                <p class="text-sm text-slate-500 mb-6">
                    {{ $isRtl ? 'ہم آپ کے منتخب کردہ ماڈل کے مطابق ماڈیولز اور ترتیبات خودکار بنائیں گے۔' : 'We will customize default workflows, terminology, and modules based on your model.' }}
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label class="relative flex flex-col p-5 border-2 rounded-xl cursor-pointer transition-all {{ $type === 'school' ? 'border-indigo-600 bg-indigo-50/40 shadow-sm' : 'border-slate-200 hover:border-slate-300' }}">
                        <input type="radio" wire:model.live="type" value="school" class="sr-only">
                        <div class="flex items-center justify-between mb-3">
                            <span class="w-10 h-10 rounded-lg bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-xl">🏫</span>
                            @if($type === 'school')
                                <span class="w-5 h-5 rounded-full bg-indigo-600 text-white flex items-center justify-center text-xs">✓</span>
                            @endif
                        </div>
                        <span class="font-bold text-lg text-slate-900">{{ $isRtl ? 'اسکول (K-12)' : 'Formal School (K-12)' }}</span>
                        <span class="text-xs text-slate-500 mt-1">
                            {{ $isRtl ? 'کلاسز، سیکشنز، سالانہ امتحانات، حاضری، اور باقاعدہ فیس چالان۔' : 'Academic years, classes, sections, promotions, daily homeroom attendance, and term report cards.' }}
                        </span>
                    </label>

                    <label class="relative flex flex-col p-5 border-2 rounded-xl cursor-pointer transition-all {{ $type === 'academy' ? 'border-indigo-600 bg-indigo-50/40 shadow-sm' : 'border-slate-200 hover:border-slate-300' }}">
                        <input type="radio" wire:model.live="type" value="academy" class="sr-only">
                        <div class="flex items-center justify-between mb-3">
                            <span class="w-10 h-10 rounded-lg bg-purple-100 text-purple-700 flex items-center justify-center font-bold text-xl">🎓</span>
                            @if($type === 'academy')
                                <span class="w-5 h-5 rounded-full bg-indigo-600 text-white flex items-center justify-center text-xs">✓</span>
                            @endif
                        </div>
                        <span class="font-bold text-lg text-slate-900">{{ $isRtl ? 'اکیڈمی / کوچنگ سینٹر' : 'Academy / Coaching Center' }}</span>
                        <span class="text-xs text-slate-500 mt-1">
                            {{ $isRtl ? 'مضامین، بیجز، کریش کورسز، انٹری ٹیسٹ، اور قسط وار فیس ماڈل۔' : 'Programs, dynamic courses, rolling batches, flexible subject enrollment, and installment fee plans.' }}
                        </span>
                    </label>
                </div>
            </div>
        @endif

        <!-- STEP 2: Legal Profile -->
        @if($currentStep === 2)
            <div>
                <h2 class="text-2xl font-bold text-slate-900 mb-2">
                    {{ $isRtl ? 'ادارے کی بنیادی معلومات' : 'Institute Basic Information' }}
                </h2>
                <p class="text-sm text-slate-500 mb-6">
                    {{ $isRtl ? 'یہ معلومات فیس چالان اور سرکاری دستاویزات پر ظاہر ہوں گی۔' : 'This information will be displayed on fee vouchers and student credentials.' }}
                </p>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">{{ $isRtl ? 'ادارے کا نام' : 'Institute Legal Name' }} *</label>
                        <input type="text" wire:model="name" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="{{ $isRtl ? 'مثلاً: آکسفورڈ گرائمر اسکول' : 'e.g. Beaconhouse Cambridge Campus' }}">
                        @error('name') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">{{ $isRtl ? 'ورک اسپیس سب ڈومین' : 'Workspace Subdomain (Slug)' }} *</label>
                            <div class="flex rounded-lg shadow-sm">
                                <input type="text" wire:model="slug" class="flex-1 px-3.5 py-2.5 border border-slate-300 rounded-s-lg focus:ring-2 focus:ring-indigo-500" placeholder="beacon">
                                <span class="inline-flex items-center px-3 rounded-e-lg border border-s-0 border-slate-300 bg-slate-50 text-slate-500 text-xs">.educore.test</span>
                            </div>
                            @error('slug') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">{{ $isRtl ? 'رجسٹریشن نمبر' : 'Registration / License No.' }}</label>
                            <input type="text" wire:model="registrationNo" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg" placeholder="REG-2026-9901">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">{{ $isRtl ? 'رابطہ ای میل' : 'Official Email' }} *</label>
                            <input type="email" wire:model="email" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg" placeholder="admin@institute.pk">
                            @error('email') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">{{ $isRtl ? 'فون نمبر' : 'Phone Number' }} *</label>
                            <input type="text" wire:model="phone" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg" placeholder="03001234567">
                            @error('phone') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">{{ $isRtl ? 'شہر' : 'City' }} *</label>
                            <input type="text" wire:model="city" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg" placeholder="Karachi">
                            @error('city') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- STEP 3: Branding & Locale -->
        @if($currentStep === 3)
            <div>
                <h2 class="text-2xl font-bold text-slate-900 mb-2">
                    {{ $isRtl ? 'برانڈنگ اور زبان کی ترتیب' : 'Branding & Language Preferences' }}
                </h2>
                <p class="text-sm text-slate-500 mb-6">
                    {{ $isRtl ? 'اپنے پورٹل کی رنگت اور بنیادی زبان کا انتخاب کریں۔' : 'Select your theme brand color and default portal language.' }}
                </p>

                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">{{ $isRtl ? 'بنیادی زبان' : 'Default Interface Language' }}</label>
                        <div class="grid grid-cols-2 gap-4">
                            <label class="p-4 border-2 rounded-xl flex items-center justify-between cursor-pointer {{ $defaultLocale === 'en' ? 'border-indigo-600 bg-indigo-50/30' : 'border-slate-200' }}">
                                <input type="radio" wire:model.live="defaultLocale" value="en" class="sr-only">
                                <span class="font-semibold text-slate-800">English (LTR)</span>
                                @if($defaultLocale === 'en') <span class="text-indigo-600 font-bold">✓</span> @endif
                            </label>

                            <label class="p-4 border-2 rounded-xl flex items-center justify-between cursor-pointer {{ $defaultLocale === 'ur' ? 'border-indigo-600 bg-indigo-50/30' : 'border-slate-200' }}">
                                <input type="radio" wire:model.live="defaultLocale" value="ur" class="sr-only">
                                <span class="font-semibold text-slate-800">اردو (RTL)</span>
                                @if($defaultLocale === 'ur') <span class="text-indigo-600 font-bold">✓</span> @endif
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">{{ $isRtl ? 'برانڈ رنگ' : 'Primary Brand Color' }}</label>
                        <div class="flex items-center gap-3">
                            <input type="color" wire:model="brandColor" class="w-12 h-12 rounded-lg cursor-pointer border border-slate-300">
                            <span class="text-sm font-mono text-slate-600">{{ $brandColor }}</span>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- STEP 4: Primary Branch -->
        @if($currentStep === 4)
            <div>
                <h2 class="text-2xl font-bold text-slate-900 mb-2">
                    {{ $isRtl ? 'مرکزی کیمپس کی تفصیلات' : 'Primary Campus / Branch Configuration' }}
                </h2>
                <p class="text-sm text-slate-500 mb-6">
                    {{ $isRtl ? 'آپ بعد میں مزید کیمپس بھی شامل کر سکتے ہیں۔' : 'Every institute has at least one main branch. You can add more campuses later.' }}
                </p>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">{{ $isRtl ? 'کیمپس کا نام' : 'Campus / Branch Name' }} *</label>
                        <input type="text" wire:model="branchName" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg" placeholder="Main Campus">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">{{ $isRtl ? 'کیمپس کا پتہ' : 'Campus Address' }}</label>
                        <textarea wire:model="branchAddress" rows="3" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg" placeholder="{{ $isRtl ? 'مثلاً: بلاک 5، گلشن اقبال، کراچی' : 'e.g. Plot 12-B, Sector F-8, Islamabad' }}"></textarea>
                    </div>
                </div>
            </div>
        @endif

        <!-- STEP 5: Academic Calendar -->
        @if($currentStep === 5)
            <div>
                <h2 class="text-2xl font-bold text-slate-900 mb-2">
                    {{ $isRtl ? 'تعلیمی کیلنڈر اور سیشن' : 'Academic Calendar & Session' }}
                </h2>
                <p class="text-sm text-slate-500 mb-6">
                    {{ $isRtl ? 'موجودہ فعال سیشن کی تاریخیں درج کریں۔' : 'Configure the dates and operational days for your current academic session.' }}
                </p>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">{{ $isRtl ? 'سیشن کا نام' : 'Session Title' }} *</label>
                        <input type="text" wire:model="sessionName" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">{{ $isRtl ? 'آغاز کی تاریخ' : 'Session Start Date' }} *</label>
                            <input type="date" wire:model="startDate" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">{{ $isRtl ? 'اختتام کی تاریخ' : 'Session End Date' }} *</label>
                            <input type="date" wire:model="endDate" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg">
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- STEP 6: Starter Cohort Templates -->
        @if($currentStep === 6)
            <div>
                <h2 class="text-2xl font-bold text-slate-900 mb-2">
                    {{ $isRtl ? 'ابتدائی کلاسز اور کورسز کے ٹیمپلیٹس' : 'Starter Classes / Program Templates' }}
                </h2>
                <p class="text-sm text-slate-500 mb-6">
                    {{ $isRtl ? 'خالی ڈیش بورڈ کے بجائے، ہم آپ کے لیے یہ معیاری ڈھانچہ پہلے سے تیار کر دیں گے۔' : 'To avoid starting with an empty workspace, we pre-seed these standard Pakistani educational cohorts.' }}
                </p>

                <div class="space-y-3">
                    @foreach($selectedTemplates as $index => $tmpl)
                        <div class="flex items-center justify-between p-3.5 rounded-lg border border-slate-200 bg-slate-50">
                            <span class="font-medium text-slate-800 text-sm">✓ {{ $tmpl }}</span>
                            <span class="text-xs bg-indigo-100 text-indigo-700 font-semibold px-2 py-0.5 rounded">
                                {{ $isRtl ? 'خودکار فعال' : 'Auto Seeded' }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- STEP 7: Fee Configuration -->
        @if($currentStep === 7)
            <div>
                <h2 class="text-2xl font-bold text-slate-900 mb-2">
                    {{ $isRtl ? 'فیس پالیسی اور چالان ترتیبات' : 'Fee Rules & Voucher Defaults' }}
                </h2>
                <p class="text-sm text-slate-500 mb-6">
                    {{ $isRtl ? 'ماہانہ فیس چالان کی آخری تاریخ اور لیٹ فائن پالیسی۔' : 'Configure monthly fee challan due dates and late payment penalties.' }}
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">{{ $isRtl ? 'ماہانہ آخری تاریخ' : 'Monthly Due Day' }} *</label>
                        <select wire:model="feeDueDay" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg">
                            <option value="5">5th of every month</option>
                            <option value="10">10th of every month</option>
                            <option value="15">15th of every month</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">{{ $isRtl ? 'تاخیری فیس جرمانہ (روپے)' : 'Late Payment Fine (PKR)' }} *</label>
                        <input type="number" wire:model="lateFeeAmount" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg" placeholder="500">
                    </div>
                </div>
            </div>
        @endif

        <!-- STEP 8: Staff Invitations -->
        @if($currentStep === 8)
            <div>
                <h2 class="text-2xl font-bold text-slate-900 mb-2">
                    {{ $isRtl ? 'اسٹاف اور ایڈمنسٹریٹر کی شمولیت' : 'Staff & Leadership Invitations' }}
                </h2>
                <p class="text-sm text-slate-500 mb-6">
                    {{ $isRtl ? 'اپنے پرنسپل یا چیف کیشئر کو دعوت نامہ بھیجیں (اختیاری)۔' : 'Invite your Principal, Academic In-charge, or Head Cashier to collaborate.' }}
                </p>

                <div class="space-y-4">
                    <div class="p-4 border border-slate-200 rounded-xl bg-slate-50/50">
                        <span class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">{{ $isRtl ? 'پرنسپل / اکیڈمک ہیڈ' : 'Principal / Academic Head' }}</span>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <input type="text" wire:model="principalName" class="px-3.5 py-2 border border-slate-300 rounded-lg text-sm" placeholder="{{ $isRtl ? 'نام' : 'Full Name' }}">
                            <input type="email" wire:model="principalEmail" class="px-3.5 py-2 border border-slate-300 rounded-lg text-sm" placeholder="{{ $isRtl ? 'ای میل' : 'Email Address' }}">
                        </div>
                    </div>

                    <div class="p-4 border border-slate-200 rounded-xl bg-slate-50/50">
                        <span class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">{{ $isRtl ? 'اکاؤنٹنٹ / کیشئر' : 'Accountant / Cashier' }}</span>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <input type="text" wire:model="cashierName" class="px-3.5 py-2 border border-slate-300 rounded-lg text-sm" placeholder="{{ $isRtl ? 'نام' : 'Full Name' }}">
                            <input type="email" wire:model="cashierEmail" class="px-3.5 py-2 border border-slate-300 rounded-lg text-sm" placeholder="{{ $isRtl ? 'ای میل' : 'Email Address' }}">
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- STEP 9: Completion & Launch -->
        @if($currentStep === 9)
            <div class="text-center py-6">
                <div class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center text-3xl mx-auto mb-4 font-bold">
                    ✓
                </div>
                <h2 class="text-2xl font-bold text-slate-900 mb-2">
                    {{ $isRtl ? 'مبارک ہو! آپ کا ورک اسپیس تیار ہے' : 'Congratulations! Your Workspace is Ready' }}
                </h2>
                <p class="text-sm text-slate-600 max-w-md mx-auto mb-6">
                    {{ $isRtl ? 'آپ کا تعلیمی پورٹل، ڈیٹابیس آئسولیشن، اور ابتدائی کلاسز کامیابی کے ساتھ تشکیل پا چکے ہیں۔' : 'Your isolated tenant database context, user roles, and initial templates have been seeded successfully.' }}
                </p>

                <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4 max-w-md mx-auto mb-6 text-start">
                    <span class="block text-xs font-semibold text-indigo-700 uppercase tracking-wider mb-1">
                        {{ $isRtl ? 'ورک اسپیس کا پتہ' : 'Direct Workspace URL' }}
                    </span>
                    <a href="{{ $workspaceUrl }}" class="font-mono text-sm text-indigo-900 font-bold underline break-all">
                        {{ $workspaceUrl }}
                    </a>
                </div>

                <a href="{{ $workspaceUrl }}" class="inline-flex items-center px-6 py-3 rounded-xl bg-indigo-600 text-white font-bold text-sm shadow hover:bg-indigo-700 transition-all">
                    {{ $isRtl ? 'ڈیش بورڈ پر جائیں' : 'Enter Institute Dashboard →' }}
                </a>
            </div>
        @endif

        <!-- Action Navigation Buttons -->
        @if($currentStep < 9)
            <div class="mt-8 pt-6 border-t border-slate-200 flex items-center justify-between">
                @if($currentStep > 1)
                    <button type="button" wire:click="previousStep" class="px-5 py-2.5 rounded-lg border border-slate-300 text-slate-700 font-semibold text-sm hover:bg-slate-50 transition-all">
                        {{ $isRtl ? '← واپس' : '← Previous' }}
                    </button>
                @else
                    <div></div>
                @endif

                <button type="button" wire:click="nextStep" class="px-6 py-2.5 rounded-lg bg-indigo-600 text-white font-semibold text-sm hover:bg-indigo-700 shadow-sm transition-all flex items-center gap-2">
                    <span>{{ $currentStep === 8 ? ($isRtl ? 'ورک اسپیس مکمل کریں' : 'Finish & Launch') : ($isRtl ? 'اگلا مرحلہ →' : 'Continue →') }}</span>
                </button>
            </div>
        @endif
    </div>
</div>
