<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
    <!-- Header -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">
                {{ $isRtl ? 'ماہانہ فیس واؤچر کنسول' : 'Monthly Fee Voucher Generation Console' }}
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                {{ $isRtl ? 'سکول کے طلباء کیلئے ماہانہ فیس واؤچرز کی خودکار اور بیچ جنریشن' : 'Batch generate idempotent monthly student fee invoices, barcode generation, and accounting accruals' }}
            </p>
        </div>
    </div>

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
        <!-- Batch Parameters Form -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-4">
            <h2 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3">
                {{ $isRtl ? 'بیچ جنریشن پیرامیٹرز' : 'Batch Voucher Parameters' }}
            </h2>

            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Campus / Branch</label>
                <select wire:model.live="branchId" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}">{{ $b->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Academic Session</label>
                <select wire:model.live="sessionId" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                    @foreach($sessions as $s)
                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Target Class</label>
                <select wire:model.live="classId" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                    @foreach($classes as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Billing Period (YYYY-MM)</label>
                <input type="month" wire:model.live="billingPeriod" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-semibold text-slate-600 uppercase mb-1">Issue Date</label>
                    <input type="date" wire:model.live="issueDate" class="w-full px-2.5 py-1.5 text-xs border border-slate-300 rounded-lg">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-slate-600 uppercase mb-1">Due Date</label>
                    <input type="date" wire:model.live="dueDate" class="w-full px-2.5 py-1.5 text-xs border border-slate-300 rounded-lg">
                </div>
            </div>

            <div class="pt-3">
                <button 
                    type="button" 
                    wire:click="generateBatch" 
                    wire:loading.attr="disabled"
                    class="w-full py-3 px-4 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white font-bold rounded-lg text-sm shadow transition flex items-center justify-center gap-2"
                >
                    <span wire:loading.remove>
                        ⚡ {{ $isRtl ? 'واؤچرز تیار کریں' : 'Generate Class Vouchers' }}
                    </span>
                    <span wire:loading>
                        ⏳ {{ $isRtl ? 'تیاری جاری ہے...' : 'Generating Vouchers...' }}
                    </span>
                </button>
            </div>
        </div>

        <!-- Recent Invoices Table -->
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-4 border-b border-slate-200 bg-slate-50/75">
                <h2 class="font-bold text-slate-900 text-sm">{{ $isRtl ? 'حالیہ تیار شدہ واؤچرز' : 'Recently Generated Invoices' }}</h2>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600">
                    <thead class="bg-slate-50 uppercase font-semibold text-slate-500 border-b border-slate-200">
                        <tr>
                            <th class="py-3 px-4">Voucher #</th>
                            <th class="py-3 px-4">Student</th>
                            <th class="py-3 px-4">Period</th>
                            <th class="py-3 px-4">Due Date</th>
                            <th class="py-3 px-4 text-right">Amount</th>
                            <th class="py-3 px-4 text-center">Status</th>
                            <th class="py-3 px-4 text-center">Print</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($recentInvoices as $inv)
                            <tr class="hover:bg-slate-50">
                                <td class="py-2.5 px-4 font-mono font-medium text-slate-900">{{ $inv->invoice_number }}</td>
                                <td class="py-2.5 px-4 font-medium">{{ $inv->student->first_name ?? '' }} {{ $inv->student->last_name ?? '' }}</td>
                                <td class="py-2.5 px-4">{{ $inv->billing_period }}</td>
                                <td class="py-2.5 px-4">{{ $inv->due_date->format('Y-m-d') }}</td>
                                <td class="py-2.5 px-4 text-right font-mono font-bold">PKR {{ number_format($inv->total_amount, 2) }}</td>
                                <td class="py-2.5 px-4 text-center">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $inv->status === 'paid' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                        {{ $inv->status }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-4 text-center space-x-1">
                                    <a href="{{ route('tenant.finance.voucher-print', $inv) }}" target="_blank" class="px-2 py-0.5 bg-slate-100 hover:bg-slate-200 rounded text-[10px] font-semibold">🖨️ 3-Copy</a>
                                    <a href="{{ route('tenant.finance.voucher-print', ['invoice' => $inv, 'format' => 'pdf']) }}" target="_blank" class="px-2 py-0.5 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 rounded text-[10px] font-semibold">PDF</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-8 text-center text-slate-400">
                                    {{ $isRtl ? 'کوئی واؤچر تیار نہیں ہے۔' : 'No invoices generated yet.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
