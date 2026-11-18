<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">SaaS Subscription Plans</h1>
            <p class="text-sm text-slate-500 mt-1">Manage EduCore pricing tiers, branch/student quotas, and module feature gates</p>
        </div>
        <button type="button" wire:click="seedDefaults" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-lg text-xs font-bold">
            ⚡ Seed Default Tiers
        </button>
    </div>

    @if($successMessage)
        <div class="bg-emerald-50 border border-emerald-300 text-emerald-900 px-4 py-3 rounded-lg text-sm">✓ {{ $successMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="bg-rose-50 border border-rose-300 text-rose-900 px-4 py-3 rounded-lg text-sm">✕ {{ $errorMessage }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-3">
            <h2 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-2">Create Plan Tier</h2>
            <input type="text" wire:model.live="code" placeholder="Code (e.g. pro)" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg font-mono">
            <input type="text" wire:model.live="name" placeholder="Display Name" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
            <div class="grid grid-cols-2 gap-2">
                <select wire:model.live="billingCycle" class="px-3 py-2 text-xs border border-slate-300 rounded-lg">
                    <option value="monthly">Monthly</option>
                    <option value="yearly">Yearly</option>
                </select>
                <input type="number" wire:model.live="pricePkr" placeholder="Price PKR" class="px-3 py-2 text-xs border border-slate-300 rounded-lg">
            </div>
            <div class="grid grid-cols-2 gap-2">
                <input type="number" wire:model.live="maxBranches" placeholder="Max Branches" class="px-3 py-2 text-xs border border-slate-300 rounded-lg">
                <input type="number" wire:model.live="maxStudents" placeholder="Max Students" class="px-3 py-2 text-xs border border-slate-300 rounded-lg">
            </div>
            <button type="button" wire:click="createPlan" class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg text-sm">
                Save Plan
            </button>
        </div>

        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50 uppercase font-semibold text-slate-500 border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-4">Code</th>
                        <th class="py-3 px-4">Plan Name</th>
                        <th class="py-3 px-4">Cycle</th>
                        <th class="py-3 px-4 text-right">Price (PKR)</th>
                        <th class="py-3 px-4 text-center">Quotas (Br / Stu)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($plans as $plan)
                        <tr class="hover:bg-slate-50">
                            <td class="py-3 px-4 font-mono font-bold text-slate-900">{{ $plan->code }}</td>
                            <td class="py-3 px-4 font-semibold">{{ $plan->name }}</td>
                            <td class="py-3 px-4 uppercase">{{ $plan->billing_cycle }}</td>
                            <td class="py-3 px-4 text-right font-mono font-bold">PKR {{ number_format($plan->price_pkr, 2) }}</td>
                            <td class="py-3 px-4 text-center">{{ $plan->max_branches }} / {{ $plan->max_students }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-slate-400">No subscription plans configured.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
