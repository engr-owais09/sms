<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
    <!-- Header & Tabs -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">
                {{ __('sms.finance.title') }}
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                {{ __('sms.finance.subtitle') }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button type="button" wire:click="$set('activeTab', 'expenses')" class="px-4 py-2 rounded-lg text-xs font-bold {{ $activeTab === 'expenses' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                💸 {{ __('sms.finance.tab_expenses') }}
            </button>
            <button type="button" wire:click="$set('activeTab', 'refunds')" class="px-4 py-2 rounded-lg text-xs font-bold {{ $activeTab === 'refunds' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                ↩️ {{ __('sms.finance.tab_refunds') }}
            </button>
            <button type="button" wire:click="$set('activeTab', 'concessions')" class="px-4 py-2 rounded-lg text-xs font-bold {{ $activeTab === 'concessions' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                🎖️ {{ __('sms.finance.tab_concessions') }}
            </button>
        </div>
    </div>

    @if($successMessage)
        <div class="bg-emerald-50 border border-emerald-300 text-emerald-900 px-4 py-3 rounded-lg text-sm">✓ {{ $successMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="bg-rose-50 border border-rose-300 text-rose-900 px-4 py-3 rounded-lg text-sm">✕ {{ $errorMessage }}</div>
    @endif

    <!-- TAB 1: EXPENSES -->
    @if($activeTab === 'expenses')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-4">
                <h2 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-2">{{ __('sms.finance.post_expense') }}</h2>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.finance.payee_name') }}</label>
                    <input type="text" wire:model="payeeName" placeholder="{{ __('sms.finance.payee_placeholder') }}" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.finance.amount_pkr') }}</label>
                        <input type="number" step="0.01" wire:model="expenseAmount" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.finance.method') }}</label>
                        <select wire:model="paymentMethod" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                            <option value="cash">{{ __('sms.finance.method_cash') }}</option>
                            <option value="bank_transfer">{{ __('sms.finance.method_bank') }}</option>
                            <option value="cheque">{{ __('sms.finance.method_cheque') }}</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.finance.debit_account') }}</label>
                    <select wire:model="debitAccountId" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                        @foreach($expenseAccounts as $acc)
                            <option value="{{ $acc->id }}">{{ $acc->account_code }} - {{ $acc->account_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.finance.credit_account') }}</label>
                    <select wire:model="creditAccountId" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                        @foreach($assetAccounts as $acc)
                            <option value="{{ $acc->id }}">{{ $acc->account_code }} - {{ $acc->account_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.finance.description') }}</label>
                    <input type="text" wire:model="expenseDescription" placeholder="{{ __('sms.finance.description_placeholder') }}" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                </div>
                <button type="button" wire:click="createExpense" class="w-full py-2.5 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg text-sm">
                    {{ __('sms.finance.post_expense') }}
                </button>
            </div>

            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-start text-xs text-slate-600">
                        <thead class="bg-slate-50 uppercase font-semibold text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="py-3 px-4 text-start">{{ __('sms.finance.col_voucher_no') }}</th>
                                <th class="py-3 px-4 text-start">{{ __('sms.finance.col_payee') }}</th>
                                <th class="py-3 px-4 text-start">{{ __('sms.finance.col_accounts') }}</th>
                                <th class="py-3 px-4 text-end">{{ __('sms.finance.col_amount') }}</th>
                                <th class="py-3 px-4 text-start">{{ __('sms.common.status') }}</th>
                                <th class="py-3 px-4 text-center">{{ __('sms.common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($expenses as $exp)
                                <tr>
                                    <td class="py-3 px-4 font-mono font-bold text-indigo-700">{{ $exp->voucher_number }}</td>
                                    <td class="py-3 px-4 font-bold text-slate-900">{{ $exp->payee_name }}</td>
                                    <td class="py-3 px-4">{{ $exp->debitAccount->account_code ?? '' }} / {{ $exp->creditAccount->account_code ?? '' }}</td>
                                    <td class="py-3 px-4 text-end font-mono font-bold">PKR {{ number_format($exp->amount, 2) }}</td>
                                    <td class="py-3 px-4 uppercase font-bold">{{ $exp->status }}</td>
                                    <td class="py-3 px-4 text-center">
                                        @if($exp->status === 'posted')
                                            <button type="button" wire:click="voidExpense('{{ $exp->id }}')" class="text-rose-600 font-bold hover:underline">{{ __('sms.finance.void') }}</button>
                                        @else
                                            <span class="text-slate-400">{{ __('sms.finance.voided') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="py-6 text-center text-slate-400">{{ __('sms.finance.no_expenses') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <!-- TAB 2: REFUNDS (MAKER-CHECKER) -->
    @if($activeTab === 'refunds')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-4">
                <h2 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-2">{{ __('sms.finance.request_refund') }}</h2>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.finance.payment_receipt') }}</label>
                    <select wire:model="selectedPaymentId" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                        <option value="">{{ __('sms.finance.select_receipt') }}</option>
                        @foreach($payments as $pay)
                            <option value="{{ $pay->id }}">#{{ $pay->receipt_number }} — {{ $pay->student->full_name ?? '' }} (PKR {{ number_format($pay->amount_tendered, 2) }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.finance.refund_amount') }}</label>
                        <input type="number" step="0.01" wire:model="refundAmount" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.finance.method') }}</label>
                        <select wire:model="refundMethod" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                            <option value="cash">{{ __('sms.finance.method_cash') }}</option>
                            <option value="bank_transfer">{{ __('sms.finance.method_bank') }}</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.finance.refund_reason') }}</label>
                    <input type="text" wire:model="refundReason" placeholder="{{ __('sms.finance.refund_reason_placeholder') }}" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                </div>
                <button type="button" wire:click="requestRefund" class="w-full py-2.5 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg text-sm">
                    {{ __('sms.finance.submit_refund') }}
                </button>
            </div>

            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-start text-xs text-slate-600">
                        <thead class="bg-slate-50 uppercase font-semibold text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="py-3 px-4 text-start">{{ __('sms.finance.col_refund_no') }}</th>
                                <th class="py-3 px-4 text-start">{{ __('sms.finance.col_student') }}</th>
                                <th class="py-3 px-4 text-end">{{ __('sms.finance.col_requested_net') }}</th>
                                <th class="py-3 px-4 text-start">{{ __('sms.common.status') }}</th>
                                <th class="py-3 px-4 text-center">{{ __('sms.finance.col_checker') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($refunds as $ref)
                                <tr>
                                    <td class="py-3 px-4 font-mono font-bold text-indigo-700">{{ $ref->refund_number }}</td>
                                    <td class="py-3 px-4 font-bold text-slate-900">{{ $ref->student->full_name ?? __('sms.common.none') }}</td>
                                    <td class="py-3 px-4 text-end font-mono">
                                        PKR {{ number_format($ref->requested_amount, 2) }}
                                        <span class="block text-[10px] text-slate-400">{{ __('sms.finance.net') }}: PKR {{ number_format($ref->net_payout_amount, 2) }}</span>
                                    </td>
                                    <td class="py-3 px-4 uppercase font-bold">{{ $ref->status }}</td>
                                    <td class="py-3 px-4 text-center">
                                        <div class="inline-flex items-center gap-2">
                                            @if($ref->status === 'pending_approval')
                                                <button type="button" wire:click="approveRefund('{{ $ref->id }}')" class="px-2 py-1 bg-emerald-600 text-white rounded text-[11px] font-bold">{{ __('sms.finance.approve') }}</button>
                                                <button type="button" wire:click="rejectRefund('{{ $ref->id }}')" class="px-2 py-1 bg-rose-600 text-white rounded text-[11px] font-bold">{{ __('sms.finance.reject') }}</button>
                                            @elseif($ref->status === 'approved')
                                                <button type="button" wire:click="disburseRefund('{{ $ref->id }}')" class="px-2 py-1 bg-indigo-600 text-white rounded text-[11px] font-bold">{{ __('sms.finance.disburse') }}</button>
                                            @else
                                                <span class="text-slate-400">{{ ucfirst($ref->status) }}</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-6 text-center text-slate-400">{{ __('sms.finance.no_refunds') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <!-- TAB 3: FEE CONCESSIONS -->
    @if($activeTab === 'concessions')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="space-y-6">
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-3">
                    <h2 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2">{{ __('sms.finance.create_policy') }}</h2>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="text" wire:model="discountCode" placeholder="SIBLING_15" class="px-3 py-2 text-xs border border-slate-300 rounded-lg font-mono uppercase">
                        <input type="text" wire:model="discountName" placeholder="Sibling Concession" class="px-3 py-2 text-xs border border-slate-300 rounded-lg">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <select wire:model="discountType" class="px-3 py-2 text-xs border border-slate-300 rounded-lg">
                            <option value="percentage">{{ __('sms.finance.percentage') }}</option>
                            <option value="fixed_amount">{{ __('sms.finance.fixed_pkr') }}</option>
                        </select>
                        <input type="number" step="0.01" wire:model="discountValue" placeholder="{{ __('sms.finance.value') }}" class="px-3 py-2 text-xs border border-slate-300 rounded-lg font-mono">
                    </div>
                    <select wire:model="stackingRule" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg">
                        <option value="highest_wins">{{ __('sms.finance.highest_wins') }}</option>
                        <option value="sum_capped">{{ __('sms.finance.sum_capped') }}</option>
                        <option value="compound">{{ __('sms.finance.compound') }}</option>
                    </select>
                    <button type="button" wire:click="createDiscountPolicy" class="w-full py-2 bg-slate-900 text-white text-xs font-bold rounded-lg">{{ __('sms.finance.save_policy') }}</button>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-3">
                    <h2 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2">{{ __('sms.finance.grant_concession') }}</h2>
                    <select wire:model="concessionStudentId" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg">
                        <option value="">{{ __('sms.finance.select_student') }}</option>
                        @foreach($students as $stu)
                            <option value="{{ $stu->id }}">{{ $stu->full_name }} ({{ $stu->admission_number }})</option>
                        @endforeach
                    </select>
                    <select wire:model="concessionDiscountId" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg">
                        <option value="">{{ __('sms.finance.select_policy') }}</option>
                        @foreach($discounts as $d)
                            <option value="{{ $d->id }}">{{ $d->name }} ({{ $d->discount_value }} {{ $d->discount_type === 'percentage' ? '%' : 'PKR' }})</option>
                        @endforeach
                    </select>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="date" wire:model="concessionValidFrom" class="px-3 py-2 text-xs border border-slate-300 rounded-lg">
                        <input type="date" wire:model="concessionValidUntil" class="px-3 py-2 text-xs border border-slate-300 rounded-lg">
                    </div>
                    <input type="text" wire:model="concessionReason" placeholder="{{ __('sms.finance.concession_reason_placeholder') }}" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg">
                    <button type="button" wire:click="assignStudentConcession" class="w-full py-2 bg-emerald-600 text-white text-xs font-bold rounded-lg">{{ __('sms.finance.grant_btn') }}</button>
                </div>
            </div>

            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-start text-xs text-slate-600">
                        <thead class="bg-slate-50 uppercase font-semibold text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="py-3 px-4 text-start">{{ __('sms.finance.col_student') }}</th>
                                <th class="py-3 px-4 text-start">{{ __('sms.finance.col_policy') }}</th>
                                <th class="py-3 px-4 text-start">{{ __('sms.finance.col_validity') }}</th>
                                <th class="py-3 px-4 text-start">{{ __('sms.common.status') }}</th>
                                <th class="py-3 px-4 text-center">{{ __('sms.common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($studentDiscounts as $sd)
                                <tr>
                                    <td class="py-3 px-4 font-bold text-slate-900">{{ $sd->student->full_name ?? __('sms.common.none') }}</td>
                                    <td class="py-3 px-4">{{ $sd->feeDiscount->name ?? __('sms.common.none') }}</td>
                                    <td class="py-3 px-4 font-mono">{{ $sd->valid_from?->format('Y-m-d') }} → {{ $sd->valid_until?->format('Y-m-d') ?? __('sms.common.ongoing') }}</td>
                                    <td class="py-3 px-4 uppercase font-bold">{{ $sd->status }}</td>
                                    <td class="py-3 px-4 text-center">
                                        @if($sd->status === 'active')
                                            <button type="button" wire:click="revokeStudentConcession('{{ $sd->id }}')" class="text-rose-600 font-bold hover:underline">{{ __('sms.finance.revoke') }}</button>
                                        @else
                                            <span class="text-slate-400">{{ __('sms.finance.revoked') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-6 text-center text-slate-400">{{ __('sms.finance.no_concessions') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
