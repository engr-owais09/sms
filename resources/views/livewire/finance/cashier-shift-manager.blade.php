<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
    <!-- Header -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">
                {{ $isRtl ? 'کیشیر شفٹ اور بلائنڈ ریکونسیلی ایشن' : 'Cashier Shifts & Blind Reconciliation' }}
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                {{ $isRtl ? 'شفٹ کا آغاز، کاؤنٹر نقد گنتی اور سپروائزر تصدیق' : 'Shift opening, blind denomination counting, supervisor reconciliation, and variance GL posting' }}
            </p>
        </div>
    </div>

    <!-- Feedback Alerts -->
    @if($successMessage)
        <div class="bg-emerald-50 border border-emerald-300 text-emerald-900 px-4 py-3 rounded-lg flex items-center gap-2">
            <span class="text-emerald-600 font-bold">✓</span>
            <span>{{ $successMessage }}</span>
        </div>
    @endif

    @if($errorMessage)
        <div class="bg-rose-50 border border-rose-300 text-rose-900 px-4 py-3 rounded-lg flex items-center gap-2">
            <span class="text-rose-600 font-bold">✕</span>
            <span>{{ $errorMessage }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Shift Operations Column -->
        <div class="lg:col-span-1 space-y-6">
            @if(!$activeSession)
                <!-- Open New Shift Card -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-4">
                    <h2 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3">
                        {{ $isRtl ? 'نئی کیشیر شفٹ کھولیں' : 'Open New Shift' }}
                    </h2>

                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-600 mb-1">
                            {{ $isRtl ? 'افتتاحی فلوٹ / نقد رقم (روپے)' : 'Opening Cash Float (PKR)' }}
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400 font-bold text-sm">PKR</span>
                            <input 
                                type="number" 
                                step="0.01" 
                                wire:model.live="openingFloat" 
                                class="w-full pl-14 pr-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-slate-900 font-bold focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-600 mb-1">
                            {{ $isRtl ? 'نوٹس / تفصیلات' : 'Opening Notes' }}
                        </label>
                        <textarea 
                            wire:model.live="openingNotes" 
                            rows="2" 
                            placeholder="Optional shift notes..."
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        ></textarea>
                    </div>

                    <button 
                        type="button" 
                        wire:click="openShift" 
                        class="w-full py-2.5 px-4 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-lg shadow-sm transition flex items-center justify-center gap-2"
                    >
                        🟢 {{ $isRtl ? 'شفٹ شروع کریں' : 'Start Cashier Shift' }}
                    </button>
                </div>
            @else
                <!-- Active Shift Status Card -->
                <div class="bg-white rounded-xl shadow-sm border border-emerald-200 p-5 space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <h2 class="text-base font-bold text-slate-900">
                            {{ $isRtl ? 'فعال کیشیر شفٹ' : 'Current Active Shift' }}
                        </h2>
                        <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                            {{ strtoupper($activeSession->status) }}
                        </span>
                    </div>

                    <div class="space-y-2 text-sm text-slate-600">
                        <div class="flex justify-between">
                            <span class="text-slate-500">{{ $isRtl ? 'سیشن کوڈ:' : 'Session Code:' }}</span>
                            <span class="font-mono font-bold text-slate-900">{{ $activeSession->session_code }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">{{ $isRtl ? 'آغاز کا وقت:' : 'Opened At:' }}</span>
                            <span>{{ $activeSession->opened_at->format('Y-m-d H:i') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">{{ $isRtl ? 'افتتاحی فلوٹ:' : 'Opening Float:' }}</span>
                            <span class="font-semibold text-slate-900">PKR {{ number_format($activeSession->opening_float, 2) }}</span>
                        </div>
                    </div>

                    <div class="pt-2">
                        <button 
                            type="button" 
                            wire:click="openCloseModal" 
                            class="w-full py-2.5 px-4 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-lg shadow-sm transition flex items-center justify-center gap-2"
                        >
                            🔒 {{ $isRtl ? 'بلائنڈ کاؤنٹ سے شفٹ بند کریں' : 'Close Shift (Blind Count)' }}
                        </button>
                    </div>
                </div>
            @endif

            <!-- Blind Count Denomination Modal -->
            @if($showBlindCloseModal)
                <div class="bg-slate-50 rounded-xl border border-indigo-200 p-5 space-y-4 shadow-sm">
                    <div class="flex justify-between items-center border-b border-slate-200 pb-2">
                        <h3 class="font-bold text-slate-900 text-sm">
                            {{ $isRtl ? 'فزیکل کیش کاؤنٹر (بلائنڈ ریکونسیلی ایشن)' : 'Physical Cash Denomination Count' }}
                        </h3>
                        <button type="button" wire:click="$set('showBlindCloseModal', false)" class="text-slate-400 hover:text-slate-600 text-sm font-bold">✕</button>
                    </div>

                    <p class="text-xs text-slate-500">
                        {{ $isRtl ? 'براہ کرم دراز میں موجود تمام کرنسی نوٹوں اور سکوں کی تعداد درج کریں۔' : 'Enter actual physical currency note counts from your cash drawer.' }}
                    </p>

                    <div class="grid grid-cols-2 gap-3 text-xs">
                        <div>
                            <label class="block text-slate-600 font-semibold mb-1">Rs. 5000 Notes</label>
                            <input type="number" min="0" wire:model.live="note5000" class="w-full px-2 py-1.5 border border-slate-300 rounded bg-white text-right font-mono font-bold">
                        </div>
                        <div>
                            <label class="block text-slate-600 font-semibold mb-1">Rs. 1000 Notes</label>
                            <input type="number" min="0" wire:model.live="note1000" class="w-full px-2 py-1.5 border border-slate-300 rounded bg-white text-right font-mono font-bold">
                        </div>
                        <div>
                            <label class="block text-slate-600 font-semibold mb-1">Rs. 500 Notes</label>
                            <input type="number" min="0" wire:model.live="note500" class="w-full px-2 py-1.5 border border-slate-300 rounded bg-white text-right font-mono font-bold">
                        </div>
                        <div>
                            <label class="block text-slate-600 font-semibold mb-1">Rs. 100 Notes</label>
                            <input type="number" min="0" wire:model.live="note100" class="w-full px-2 py-1.5 border border-slate-300 rounded bg-white text-right font-mono font-bold">
                        </div>
                        <div>
                            <label class="block text-slate-600 font-semibold mb-1">Rs. 50 Notes</label>
                            <input type="number" min="0" wire:model.live="note50" class="w-full px-2 py-1.5 border border-slate-300 rounded bg-white text-right font-mono font-bold">
                        </div>
                        <div>
                            <label class="block text-slate-600 font-semibold mb-1">Rs. 20 Notes</label>
                            <input type="number" min="0" wire:model.live="note20" class="w-full px-2 py-1.5 border border-slate-300 rounded bg-white text-right font-mono font-bold">
                        </div>
                        <div>
                            <label class="block text-slate-600 font-semibold mb-1">Rs. 10 Notes</label>
                            <input type="number" min="0" wire:model.live="note10" class="w-full px-2 py-1.5 border border-slate-300 rounded bg-white text-right font-mono font-bold">
                        </div>
                        <div>
                            <label class="block text-slate-600 font-semibold mb-1">Coins & Change (PKR)</label>
                            <input type="number" step="0.01" min="0" wire:model.live="coins" class="w-full px-2 py-1.5 border border-slate-300 rounded bg-white text-right font-mono font-bold">
                        </div>
                    </div>

                    <div class="bg-indigo-50 border border-indigo-200 rounded p-3 flex justify-between items-center text-sm">
                        <span class="font-semibold text-indigo-900">{{ $isRtl ? 'کل شمار شدہ نقد:' : 'Total Counted Cash:' }}</span>
                        <span class="font-bold text-indigo-900 font-mono text-base">PKR {{ number_format($countedTotal, 2) }}</span>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">{{ $isRtl ? 'اختتامی نوٹس' : 'Closing Remarks' }}</label>
                        <input type="text" wire:model.live="closingNotes" placeholder="Optional comments..." class="w-full px-3 py-1.5 text-xs border border-slate-300 rounded bg-white">
                    </div>

                    <button 
                        type="button" 
                        wire:click="submitBlindClose" 
                        class="w-full py-2 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg text-sm shadow transition"
                    >
                        🔒 {{ $isRtl ? 'شمار مکمل اور شفٹ بند کریں' : 'Confirm Blind Close' }}
                    </button>
                </div>
            @endif
        </div>

        <!-- Shift History & Supervisor Verification Column -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-4 border-b border-slate-200 bg-slate-50/75 flex justify-between items-center">
                    <h2 class="font-bold text-slate-900 text-sm">
                        {{ $isRtl ? 'حالیہ کیشیر شفٹ سیشنز اور تصدیق' : 'Recent Shift Sessions & Supervisor Verification' }}
                    </h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-600">
                        <thead class="bg-slate-50 uppercase font-semibold text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="py-3 px-3">{{ $isRtl ? 'سیشن' : 'Session' }}</th>
                                <th class="py-3 px-3">{{ $isRtl ? 'کیشیر' : 'Cashier' }}</th>
                                <th class="py-3 px-3">{{ $isRtl ? 'حیثیت' : 'Status' }}</th>
                                <th class="py-3 px-3 text-right">{{ $isRtl ? 'شمار شدہ' : 'Counted' }}</th>
                                <th class="py-3 px-3 text-right">{{ $isRtl ? 'متوقع' : 'Expected' }}</th>
                                <th class="py-3 px-3 text-right">{{ $isRtl ? 'فرق' : 'Variance' }}</th>
                                <th class="py-3 px-3 text-center">{{ $isRtl ? 'کارروائی' : 'Action' }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($recentSessions as $session)
                                <tr class="hover:bg-slate-50">
                                    <td class="py-3 px-3 font-mono font-medium text-slate-900">
                                        {{ $session->session_code }}
                                    </td>
                                    <td class="py-3 px-3">
                                        {{ $session->cashier->name ?? 'User' }}
                                    </td>
                                    <td class="py-3 px-3">
                                        @if($session->status === 'open')
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">OPEN</span>
                                        @elseif($session->status === 'closed')
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800">CLOSED</span>
                                        @else
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-800">VERIFIED</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-3 text-right font-medium">
                                        {{ $session->actual_counted_cash !== null ? 'PKR ' . number_format($session->actual_counted_cash, 2) : '—' }}
                                    </td>
                                    <td class="py-3 px-3 text-right font-medium">
                                        PKR {{ number_format($session->expected_cash_balance, 2) }}
                                    </td>
                                    <td class="py-3 px-3 text-right font-bold {{ (float) $session->variance_amount < 0 ? 'text-rose-600' : ((float) $session->variance_amount > 0 ? 'text-emerald-600' : 'text-slate-700') }}">
                                        {{ $session->variance_amount !== null ? 'PKR ' . number_format($session->variance_amount, 2) : '—' }}
                                    </td>
                                    <td class="py-3 px-3 text-center">
                                        @if($session->status === 'closed')
                                            <button 
                                                type="button" 
                                                wire:click="verifySession('{{ $session->id }}')" 
                                                class="px-2.5 py-1 bg-indigo-600 hover:bg-indigo-700 text-white rounded font-medium text-[11px]"
                                            >
                                                ✓ {{ $isRtl ? 'تصدیق' : 'Verify' }}
                                            </button>
                                        @else
                                            <span class="text-slate-400 text-[11px]">Completed</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-6 text-center text-slate-400">
                                        {{ $isRtl ? 'کوئی سابقہ شفٹ سیشن موجود نہیں۔' : 'No shift sessions found.' }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
