<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <h1 class="text-2xl font-bold text-slate-900">Tenant Subscription & Billing Console</h1>
        <p class="text-sm text-slate-500 mt-1">Assign SaaS plans, generate billing invoices, and record 1Bill / JazzCash / Bank settlements</p>
    </div>

    @if($successMessage)
        <div class="bg-emerald-50 border border-emerald-300 text-emerald-900 px-4 py-3 rounded-lg text-sm">✓ {{ $successMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="bg-rose-50 border border-rose-300 text-rose-900 px-4 py-3 rounded-lg text-sm">✕ {{ $errorMessage }}</div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <div>
            <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Tenant</label>
            <select wire:model.live="selectedTenantId" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                @foreach($tenants as $t)
                    <option value="{{ $t->id }}">{{ $t->name }} ({{ $t->status }})</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Subscription Plan</label>
            <select wire:model.live="selectedPlanId" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                @foreach($plans as $p)
                    <option value="{{ $p->id }}">{{ $p->name }} (PKR {{ number_format($p->price_pkr, 0) }})</option>
                @endforeach
            </select>
        </div>

        <label class="flex items-center gap-2 text-xs font-semibold text-slate-700 pb-2">
            <input type="checkbox" wire:model.live="startTrial" class="rounded text-indigo-600">
            Start 14-Day Trial
        </label>

        <button type="button" wire:click="assignPlanAndGenerateInvoice" class="py-2.5 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg text-sm">
            Assign Plan & Bill
        </button>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-4 border-b border-slate-200 bg-slate-50 flex justify-between items-center">
            <h2 class="font-bold text-slate-900 text-sm">Recent SaaS Subscription Invoices</h2>
            <select wire:model.live="paymentMethod" class="text-xs px-2.5 py-1 border border-slate-300 rounded">
                <option value="1bill">1Link 1Bill</option>
                <option value="bank_transfer">Bank Transfer</option>
                <option value="jazzcash">JazzCash</option>
                <option value="easypaisa">Easypaisa</option>
                <option value="card">Card</option>
            </select>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50 uppercase font-semibold text-slate-500 border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-4">Invoice #</th>
                        <th class="py-3 px-4">Tenant</th>
                        <th class="py-3 px-4">Plan</th>
                        <th class="py-3 px-4 text-right">Total (PKR)</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($invoices as $inv)
                        <tr class="hover:bg-slate-50">
                            <td class="py-3 px-4 font-mono font-bold text-slate-900">{{ $inv->invoice_number }}</td>
                            <td class="py-3 px-4 font-semibold">{{ $inv->tenant->name ?? '' }}</td>
                            <td class="py-3 px-4">{{ $inv->subscription->plan->name ?? '' }}</td>
                            <td class="py-3 px-4 text-right font-mono font-bold">PKR {{ number_format($inv->total_amount_pkr, 2) }}</td>
                            <td class="py-3 px-4 text-center">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $inv->status === 'paid' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                    {{ $inv->status }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                @if($inv->status === 'unpaid')
                                    <button type="button" wire:click="markInvoicePaid('{{ $inv->id }}')" class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded font-bold text-[11px]">
                                        ✓ Mark Paid
                                    </button>
                                @else
                                    <span class="text-slate-400">Settled</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-400">No SaaS subscription invoices found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
