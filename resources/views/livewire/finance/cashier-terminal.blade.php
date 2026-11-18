<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
    <!-- Header & Active Session Info -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">
                {{ $isRtl ? 'کیش کاؤنٹر ٹرمینل' : 'Cashier Counter Terminal' }}
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                {{ $isRtl ? 'تیز رفتار واؤچر بارکوڈ اسکیننگ اور فیس وصولی' : 'Fast counter POS with barcode scanning, instant allocation, and thermal printing' }}
            </p>
        </div>

        <div class="flex items-center gap-3">
            @if($activeSessionCode)
                <div class="flex items-center gap-2 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold px-3 py-1.5 rounded-lg">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>{{ $isRtl ? 'فعال شفٹ سیشن:' : 'Active Shift:' }} {{ $activeSessionCode }}</span>
                </div>
            @else
                <div class="flex items-center gap-2 bg-amber-50 border border-amber-200 text-amber-800 text-xs font-semibold px-3 py-1.5 rounded-lg">
                    <span>⚠️ {{ $isRtl ? 'کوئی فعال شفٹ نہیں کھلی' : 'No Open Shift Session' }}</span>
                </div>
            @endif
        </div>
    </div>

    <!-- Notifications -->
    @if($successMessage)
        <div class="bg-emerald-50 border border-emerald-300 text-emerald-900 px-4 py-3 rounded-lg flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="text-emerald-600 font-bold">✓</span>
                <span>{{ $successMessage }}</span>
            </div>
            @if($lastPayment)
                <div class="flex items-center gap-2">
                    <a href="{{ route('tenant.finance.receipt-print', $lastPayment) }}" target="_blank" class="px-3 py-1 bg-white border border-emerald-400 text-emerald-700 text-xs font-medium rounded hover:bg-emerald-50 shadow-sm">
                        🖨️ {{ $isRtl ? '80 ملی میٹر رسید' : 'Thermal 80mm' }}
                    </a>
                    <a href="{{ route('tenant.finance.receipt-print', ['payment' => $lastPayment, 'format' => 'pdf']) }}" target="_blank" class="px-3 py-1 bg-emerald-700 text-white text-xs font-medium rounded hover:bg-emerald-800 shadow-sm">
                        📄 {{ $isRtl ? 'PDF ڈاؤنلوڈ' : 'Download PDF' }}
                    </a>
                </div>
            @endif
        </div>
    @endif

    @if($errorMessage)
        <div class="bg-rose-50 border border-rose-300 text-rose-900 px-4 py-3 rounded-lg flex items-center gap-2">
            <span class="text-rose-600 font-bold">✕</span>
            <span>{{ $errorMessage }}</span>
        </div>
    @endif

    <!-- Main POS Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left 2 Cols: Search & Invoices -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Fast Barcode / Student Search -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <label class="block text-sm font-semibold text-slate-700 mb-2">
                    {{ $isRtl ? 'بارکوڈ اسکین کریں یا داخلہ نمبر / طالب علم تلاش کریں' : 'Scan Voucher Barcode or Search Student / Admission #' }}
                </label>
                <div class="relative">
                    <input 
                        type="text" 
                        wire:model.live.debounce.300ms="searchQuery" 
                        placeholder="{{ $isRtl ? 'بارکوڈ اسکین کریں یا داخلہ نمبر ٹائپ کریں...' : 'Scan 18-digit PSID / Barcode or type student name...' }}"
                        autofocus
                        class="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-300 rounded-lg text-slate-900 placeholder-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono text-base"
                    >
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        🔍
                    </div>
                </div>
            </div>

            <!-- Student Profile Card if Selected -->
            @if($selectedStudent)
                <div class="bg-indigo-50/60 rounded-xl border border-indigo-100 p-5 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <span class="w-10 h-10 rounded-full bg-indigo-600 text-white font-bold flex items-center justify-center text-base shadow-sm">
                                {{ strtoupper(substr($selectedStudent->first_name, 0, 1)) }}
                            </span>
                            <div>
                                <h2 class="text-lg font-bold text-slate-900">
                                    {{ $selectedStudent->first_name }} {{ $selectedStudent->last_name }}
                                </h2>
                                <p class="text-xs text-slate-600">
                                    {{ $isRtl ? 'داخلہ نمبر:' : 'Admission #:' }} <span class="font-mono font-semibold">{{ $selectedStudent->admission_number }}</span>
                                    • {{ $selectedStudent->branch->name ?? 'Campus' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="text-right">
                        <span class="text-xs text-slate-500 uppercase tracking-wider block font-medium">
                            {{ $isRtl ? 'واجب الادا واؤچرز' : 'Pending Invoices' }}
                        </span>
                        <span class="text-xl font-bold text-slate-900">
                            {{ count($invoices) }}
                        </span>
                    </div>
                </div>

                <!-- Invoices List -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="p-4 border-b border-slate-200 bg-slate-50/75 flex justify-between items-center">
                        <h3 class="font-semibold text-slate-800 text-sm">
                            {{ $isRtl ? 'واجب الادا واؤچرز کی فہرست' : 'Outstanding Invoices' }}
                        </h3>
                    </div>

                    @if(empty($invoices))
                        <div class="p-8 text-center text-slate-500 text-sm">
                            🎉 {{ $isRtl ? 'کوئی واجب الادا واؤچر نہیں ہے۔' : 'No outstanding unpaid invoices found for this student.' }}
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm text-slate-600">
                                <thead class="bg-slate-50 text-xs uppercase font-semibold text-slate-500 border-b border-slate-200">
                                    <tr>
                                        <th class="py-3 px-4">{{ $isRtl ? 'انتخاب' : 'Select' }}</th>
                                        <th class="py-3 px-4">{{ $isRtl ? 'واؤچر نمبر' : 'Voucher #' }}</th>
                                        <th class="py-3 px-4">{{ $isRtl ? 'مدت' : 'Period' }}</th>
                                        <th class="py-3 px-4">{{ $isRtl ? 'آخری تاریخ' : 'Due Date' }}</th>
                                        <th class="py-3 px-4 text-right">{{ $isRtl ? 'کل رقم' : 'Total' }}</th>
                                        <th class="py-3 px-4 text-right">{{ $isRtl ? 'بقیہ رقم' : 'Remaining' }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach($invoices as $inv)
                                        <tr class="hover:bg-slate-50/80 cursor-pointer {{ $selectedInvoiceId === $inv['id'] ? 'bg-indigo-50/50' : '' }}" wire:click="selectInvoice('{{ $inv['id'] }}')">
                                            <td class="py-3 px-4">
                                                <input 
                                                    type="radio" 
                                                    name="selected_invoice" 
                                                    value="{{ $inv['id'] }}" 
                                                    wire:model.live="selectedInvoiceId"
                                                    class="text-indigo-600 focus:ring-indigo-500 h-4 w-4"
                                                >
                                            </td>
                                            <td class="py-3 px-4 font-mono font-medium text-slate-900">
                                                {{ $inv['invoice_number'] }}
                                            </td>
                                            <td class="py-3 px-4">
                                                {{ $inv['billing_period'] ?? '—' }}
                                            </td>
                                            <td class="py-3 px-4">
                                                <span class="{{ $inv['is_overdue'] ? 'text-rose-600 font-semibold' : '' }}">
                                                    {{ $inv['due_date'] }}
                                                </span>
                                                @if($inv['is_overdue'])
                                                    <span class="inline-block text-[10px] uppercase font-bold bg-rose-100 text-rose-700 px-1.5 py-0.5 rounded ml-1">
                                                        {{ $isRtl ? 'تاخیر' : 'Overdue' }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="py-3 px-4 text-right font-medium text-slate-800">
                                                PKR {{ number_format($inv['total_amount'], 2) }}
                                            </td>
                                            <td class="py-3 px-4 text-right font-bold text-slate-900">
                                                PKR {{ number_format($inv['remaining_balance'], 2) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <!-- Right 1 Col: Payment Tender Panel -->
        <div class="space-y-6">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-4">
                <h3 class="font-bold text-slate-900 text-base border-b border-slate-100 pb-3">
                    {{ $isRtl ? 'ادائیگی وصولی پینل' : 'Payment Tender' }}
                </h3>

                <!-- Amount Tendered -->
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-600 mb-1">
                        {{ $isRtl ? 'وصول شدہ رقم (روپے)' : 'Amount Tendered (PKR)' }}
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400 font-bold text-sm">PKR</span>
                        <input 
                            type="number" 
                            step="0.01" 
                            wire:model.live="amountTendered" 
                            class="w-full pl-14 pr-3 py-2.5 bg-slate-50 border border-slate-300 rounded-lg text-lg font-bold text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                    </div>
                </div>

                <!-- Payment Method -->
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-600 mb-1">
                        {{ $isRtl ? 'طریقہ ادائیگی' : 'Payment Method' }}
                    </label>
                    <select 
                        wire:model.live="paymentMethod" 
                        class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 font-medium"
                    >
                        <option value="cash">{{ $isRtl ? 'نقد (کیش)' : 'Cash' }}</option>
                        <option value="bank_transfer">{{ $isRtl ? 'بینک ٹرانسفر / 1 بل' : 'Bank Transfer / 1Bill' }}</option>
                        <option value="pos_machine">{{ $isRtl ? 'پی او ایس کارڈ مشین' : 'POS Card Terminal' }}</option>
                        <option value="cheque">{{ $isRtl ? 'چیک' : 'Cheque' }}</option>
                        <option value="online_portal">{{ $isRtl ? 'آن لائن پورٹل' : 'Online Portal' }}</option>
                    </select>
                </div>

                <!-- Instrument Reference -->
                @if($paymentMethod !== 'cash')
                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-600 mb-1">
                            {{ $isRtl ? 'ٹرانزیکشن / چیک ریفرنس' : 'Reference / Cheque #' }}
                        </label>
                        <input 
                            type="text" 
                            wire:model.live="instrumentReference" 
                            placeholder="e.g. TR-998811 or Cheque 00123"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                    </div>
                @endif

                <!-- Advance Prepayment Toggle -->
                <div class="pt-2 border-t border-slate-100">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" wire:model.live="isAdvance" class="rounded text-indigo-600 focus:ring-indigo-500 h-4 w-4">
                        <span class="text-xs font-medium text-slate-700">
                            {{ $isRtl ? 'ایڈوانس پیشگی فیس وصولی' : 'Collect as Advance Tuition' }}
                        </span>
                    </label>

                    @if($isAdvance)
                        <div class="mt-2 space-y-2">
                            <div>
                                <label class="block text-[11px] text-slate-500 uppercase">{{ $isRtl ? 'ہدف شدہ بلنگ مدت' : 'Target Billing Period (YYYY-MM)' }}</label>
                                <input type="month" wire:model.live="targetAdvancePeriod" class="w-full px-2 py-1.5 text-xs bg-slate-50 border border-slate-300 rounded">
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Action Submit -->
                <div class="pt-4">
                    <button 
                        type="button" 
                        wire:click="processPayment" 
                        wire:loading.attr="disabled"
                        class="w-full py-3 px-4 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white font-bold rounded-lg shadow-sm transition flex items-center justify-center gap-2 text-base"
                    >
                        <span wire:loading.remove>
                            💰 {{ $isRtl ? 'ادائیگی محفوظ کریں' : 'Collect & Record Payment' }}
                        </span>
                        <span wire:loading>
                            ⏳ {{ $isRtl ? 'پروسیسنگ...' : 'Processing Payment...' }}
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
