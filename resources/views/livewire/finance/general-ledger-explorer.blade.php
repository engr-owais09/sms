<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
    <!-- Header -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">
                {{ $isRtl ? 'جنرل لیجر ایکسپلورر اور رپورٹس' : 'General Ledger & Financial Reporting' }}
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                {{ $isRtl ? 'ٹرائل بیلنس، وصولیوں کی عمر بندی اور اکاؤنٹس تجزیہ' : 'Trial balance verification, defaulters aging analysis, and streaming ledger exports' }}
            </p>
        </div>

        <div class="flex items-center gap-2">
            @if($activeTab === 'trial_balance')
                <button 
                    type="button" 
                    wire:click="exportTrialBalanceCsv" 
                    class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-bold shadow-sm transition flex items-center gap-1.5"
                >
                    📥 {{ $isRtl ? 'ٹرائل بیلنس CSV ایکسپورٹ' : 'Export Trial Balance CSV' }}
                </button>
            @else
                <button 
                    type="button" 
                    wire:click="exportDefaultersCsv" 
                    class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-bold shadow-sm transition flex items-center gap-1.5"
                >
                    📥 {{ $isRtl ? 'ڈیفالٹرز فہرست CSV ایکسپورٹ' : 'Export Defaulters CSV' }}
                </button>
            @endif
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="flex border-b border-slate-200 bg-white px-4 rounded-t-xl">
        <button 
            type="button" 
            wire:click="$set('activeTab', 'trial_balance')" 
            class="py-3 px-5 text-sm font-semibold border-b-2 transition {{ $activeTab === 'trial_balance' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-800' }}"
        >
            📊 {{ $isRtl ? 'ٹرائل بیلنس (میزان آزمائش)' : 'Trial Balance' }}
        </button>
        <button 
            type="button" 
            wire:click="$set('activeTab', 'defaulters')" 
            class="py-3 px-5 text-sm font-semibold border-b-2 transition {{ $activeTab === 'defaulters' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-800' }}"
        >
            ⏳ {{ $isRtl ? 'نا دہندگان کی عمر بندی (ڈیفالٹرز)' : 'Defaulters Aging Analysis' }}
        </button>
    </div>

    @if($activeTab === 'trial_balance')
        <!-- Trial Balance View -->
        <div class="space-y-6">
            <!-- Equilibrium Status Bar -->
            <div class="p-4 rounded-xl border flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 {{ $tbData['isBalanced'] ? 'bg-emerald-50 border-emerald-200 text-emerald-900' : 'bg-rose-50 border-rose-200 text-rose-900' }}">
                <div class="flex items-center gap-2">
                    <span class="text-lg">{{ $tbData['isBalanced'] ? '⚖️' : '⚠️' }}</span>
                    <div>
                        <span class="font-bold text-sm">
                            {{ $tbData['isBalanced'] 
                                ? ($isRtl ? 'لیجر متوازن ہے — زیرو ڈرفٹ توازن تصدیق شدہ' : 'Ledger In Equilibrium — Total Debits Equal Total Credits') 
                                : ($isRtl ? 'لیجر غیر متوازن ہے — براہ کرم عدم مطابقت چیک کریں' : 'Ledger Out of Balance — Discrepancy Detected') }}
                        </span>
                        <p class="text-xs opacity-80">
                            {{ $isRtl ? 'کل ڈیبٹ:' : 'Total Debits:' }} PKR {{ number_format($tbData['totalDebits'], 2) }} | 
                            {{ $isRtl ? 'کل کریڈٹ:' : 'Total Credits:' }} PKR {{ number_format($tbData['totalCredits'], 2) }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <select wire:model.live="selectedFiscalYearId" class="text-xs px-2.5 py-1.5 rounded-lg border border-slate-300 bg-white text-slate-800 font-medium">
                        <option value="">{{ $isRtl ? 'تمام مالی سال' : 'All Fiscal Years' }}</option>
                        @foreach($fiscalYears as $fy)
                            <option value="{{ $fy->id }}">{{ $fy->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Chart of Accounts Trial Balance Table -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-600">
                        <thead class="bg-slate-50 uppercase font-semibold text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="py-3 px-4">{{ $isRtl ? 'کوڈ' : 'Account Code' }}</th>
                                <th class="py-3 px-4">{{ $isRtl ? 'کھاتہ نام' : 'Account Title' }}</th>
                                <th class="py-3 px-4">{{ $isRtl ? 'قسم' : 'Classification' }}</th>
                                <th class="py-3 px-4 text-right">{{ $isRtl ? 'ڈیبٹ (روپے)' : 'Debit (PKR)' }}</th>
                                <th class="py-3 px-4 text-right">{{ $isRtl ? 'کریڈٹ (روپے)' : 'Credit (PKR)' }}</th>
                                <th class="py-3 px-4 text-right">{{ $isRtl ? 'خالص بیلنس' : 'Net Balance' }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($tbData['accounts'] as $acc)
                                <tr class="hover:bg-slate-50">
                                    <td class="py-2.5 px-4 font-mono font-bold text-slate-900">{{ $acc['code'] }}</td>
                                    <td class="py-2.5 px-4 font-medium text-slate-800">{{ $acc['name'] }}</td>
                                    <td class="py-2.5 px-4 uppercase text-[10px] text-slate-500 font-semibold">{{ $acc['type'] }}</td>
                                    <td class="py-2.5 px-4 text-right font-mono font-medium {{ $acc['debit'] > 0 ? 'text-slate-900' : 'text-slate-400' }}">
                                        {{ number_format($acc['debit'], 2) }}
                                    </td>
                                    <td class="py-2.5 px-4 text-right font-mono font-medium {{ $acc['credit'] > 0 ? 'text-slate-900' : 'text-slate-400' }}">
                                        {{ number_format($acc['credit'], 2) }}
                                    </td>
                                    <td class="py-2.5 px-4 text-right font-mono font-bold {{ $acc['net_balance'] < 0 ? 'text-rose-600' : 'text-slate-900' }}">
                                        {{ number_format($acc['net_balance'], 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-slate-400">
                                        {{ $isRtl ? 'کوئی کھاتہ دستیاب نہیں۔' : 'No chart of accounts found.' }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="bg-slate-100 font-bold text-slate-900 border-t-2 border-slate-300">
                            <tr>
                                <td colspan="3" class="py-3 px-4 uppercase tracking-wider text-right">
                                    {{ $isRtl ? 'میزان کل:' : 'Grand Totals:' }}
                                </td>
                                <td class="py-3 px-4 text-right font-mono text-sm">
                                    PKR {{ number_format($tbData['totalDebits'], 2) }}
                                </td>
                                <td class="py-3 px-4 text-right font-mono text-sm">
                                    PKR {{ number_format($tbData['totalCredits'], 2) }}
                                </td>
                                <td class="py-3 px-4 text-right font-mono text-sm {{ $tbData['isBalanced'] ? 'text-emerald-700' : 'text-rose-700' }}">
                                    {{ $tbData['isBalanced'] ? 'BALANCED' : 'OUT OF BALANCE' }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    @else
        <!-- Defaulters Aging View -->
        <div class="space-y-6">
            <!-- Aging Bucket Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 cursor-pointer hover:border-indigo-400 transition" wire:click="$set('agingBucket', 'all')">
                    <span class="text-xs uppercase font-semibold text-slate-500 block">Total Overdue</span>
                    <span class="text-xl font-bold text-slate-900 mt-1 block">PKR {{ number_format($defaultersData['summary']['total'], 2) }}</span>
                    <span class="text-[10px] text-slate-400 mt-1 block">All overdue invoices</span>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 cursor-pointer hover:border-amber-400 transition" wire:click="$set('agingBucket', 'current')">
                    <span class="text-xs uppercase font-semibold text-amber-600 block">Current (0-30 Days)</span>
                    <span class="text-xl font-bold text-amber-900 mt-1 block">PKR {{ number_format($defaultersData['summary']['current'], 2) }}</span>
                    <span class="text-[10px] text-slate-400 mt-1 block">Recently overdue</span>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 cursor-pointer hover:border-orange-400 transition" wire:click="$set('agingBucket', '30_60')">
                    <span class="text-xs uppercase font-semibold text-orange-600 block">31-60 Days</span>
                    <span class="text-xl font-bold text-orange-900 mt-1 block">PKR {{ number_format($defaultersData['summary']['30_60'], 2) }}</span>
                    <span class="text-[10px] text-slate-400 mt-1 block">1-2 months overdue</span>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 cursor-pointer hover:border-rose-400 transition" wire:click="$set('agingBucket', '60_90')">
                    <span class="text-xs uppercase font-semibold text-rose-600 block">61-90 Days</span>
                    <span class="text-xl font-bold text-rose-900 mt-1 block">PKR {{ number_format($defaultersData['summary']['60_90'], 2) }}</span>
                    <span class="text-[10px] text-slate-400 mt-1 block">2-3 months overdue</span>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-rose-200 bg-rose-50/40 p-4 cursor-pointer hover:border-rose-500 transition" wire:click="$set('agingBucket', '90_plus')">
                    <span class="text-xs uppercase font-semibold text-rose-700 block">90+ Days Critical</span>
                    <span class="text-xl font-bold text-rose-900 mt-1 block">PKR {{ number_format($defaultersData['summary']['90_plus'], 2) }}</span>
                    <span class="text-[10px] text-rose-600 mt-1 block">Severe default</span>
                </div>
            </div>

            <!-- Invoices Table -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-600">
                        <thead class="bg-slate-50 uppercase font-semibold text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="py-3 px-4">{{ $isRtl ? 'واؤچر نمبر' : 'Voucher #' }}</th>
                                <th class="py-3 px-4">{{ $isRtl ? 'داخلہ نمبر' : 'Admission #' }}</th>
                                <th class="py-3 px-4">{{ $isRtl ? 'طالب علم' : 'Student' }}</th>
                                <th class="py-3 px-4">{{ $isRtl ? 'آخری تاریخ' : 'Due Date' }}</th>
                                <th class="py-3 px-4 text-center">{{ $isRtl ? 'تاخیر کے دن' : 'Days Overdue' }}</th>
                                <th class="py-3 px-4">{{ $isRtl ? 'عمر بندی' : 'Aging Category' }}</th>
                                <th class="py-3 px-4 text-right">{{ $isRtl ? 'واجب الادا بقایا' : 'Outstanding Balance' }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($defaultersData['records'] as $r)
                                <tr class="hover:bg-slate-50">
                                    <td class="py-2.5 px-4 font-mono font-medium text-slate-900">{{ $r['invoice_number'] }}</td>
                                    <td class="py-2.5 px-4 font-mono text-slate-700">{{ $r['admission_number'] }}</td>
                                    <td class="py-2.5 px-4 font-semibold text-slate-900">{{ $r['student_name'] }}</td>
                                    <td class="py-2.5 px-4">{{ $r['due_date'] }}</td>
                                    <td class="py-2.5 px-4 text-center font-bold text-rose-600">{{ $r['days_overdue'] }} d</td>
                                    <td class="py-2.5 px-4">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $r['bucket'] === '90_plus' ? 'bg-rose-100 text-rose-800' : ($r['bucket'] === '60_90' ? 'bg-orange-100 text-orange-800' : 'bg-amber-100 text-amber-800') }}">
                                            {{ str_replace('_', '-', $r['bucket']) }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-4 text-right font-mono font-bold text-slate-900">
                                        PKR {{ number_format($r['balance'], 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-8 text-center text-slate-400">
                                        {{ $isRtl ? 'کوئی نا دہندہ واؤچر نہیں ملا۔' : 'No overdue invoices matching filter.' }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
