<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
    <!-- Header -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">
                {{ $isRtl ? 'فیس اسٹرکچر اور کیٹیگری مینیجر' : 'Fee Structures & Categories' }}
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                {{ $isRtl ? 'فیس کیٹیگریز اور کلاس وار فیس اسٹرکچر کی تشکیل' : 'Manage revenue heads, tuition pricing models, and class-wise fee schedules' }}
            </p>
        </div>

        <div class="flex items-center gap-2">
            <button 
                type="button" 
                wire:click="$set('activeView', 'structures')" 
                class="px-4 py-2 rounded-lg text-xs font-bold transition {{ $activeView === 'structures' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}"
            >
                📋 {{ $isRtl ? 'فیس اسٹرکچرز' : 'Fee Structures' }}
            </button>
            <button 
                type="button" 
                wire:click="$set('activeView', 'categories')" 
                class="px-4 py-2 rounded-lg text-xs font-bold transition {{ $activeView === 'categories' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}"
            >
                🏷️ {{ $isRtl ? 'فیس کیٹیگریز' : 'Fee Categories' }}
            </button>
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

    @if($activeView === 'structures')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Create Structure Form -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-4">
                <h2 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3">
                    {{ $isRtl ? 'نیا فیس اسٹرکچر بنائیں' : 'Create New Fee Schedule' }}
                </h2>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Structure Title</label>
                    <input type="text" wire:model.live="structureName" placeholder="e.g. Grade 9 Monthly Tuition 2026" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Target Class</label>
                    <select wire:model.live="structureClassId" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                        <option value="">All Classes / Unassigned</option>
                        @foreach($classes as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Campus / Branch</label>
                    <select wire:model.live="structureBranchId" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                        @foreach($branches as $b)
                            <option value="{{ $b->id }}">{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Fee Items Repeater -->
                <div class="space-y-2 pt-2 border-t border-slate-100">
                    <div class="flex justify-between items-center">
                        <label class="text-xs font-bold text-slate-700 uppercase">Fee Heads / Components</label>
                        <button type="button" wire:click="addItemRow" class="text-xs text-indigo-600 font-semibold hover:underline">+ Add Head</button>
                    </div>

                    @foreach($structureItems as $idx => $item)
                        <div class="flex items-center gap-2">
                            <select wire:model.live="structureItems.{{ $idx }}.fee_category_id" class="text-xs px-2 py-1.5 border border-slate-300 rounded flex-1">
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </select>
                            <input type="number" step="10" wire:model.live="structureItems.{{ $idx }}.amount" placeholder="PKR" class="text-xs w-24 px-2 py-1.5 border border-slate-300 rounded text-right font-mono font-bold">
                            @if(count($structureItems) > 1)
                                <button type="button" wire:click="removeItemRow({{ $idx }})" class="text-rose-500 hover:text-rose-700 text-sm font-bold">✕</button>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="pt-2">
                    <button type="button" wire:click="createStructure" class="w-full py-2.5 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg text-sm shadow transition">
                        ✓ {{ $isRtl ? 'اسٹرکچر محفوظ کریں' : 'Save Fee Structure' }}
                    </button>
                </div>
            </div>

            <!-- Existing Structures List -->
            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-4 border-b border-slate-200 bg-slate-50/75">
                    <h2 class="font-bold text-slate-900 text-sm">{{ $isRtl ? 'فعال فیس اسٹرکچرز' : 'Configured Fee Structures' }}</h2>
                </div>

                <div class="divide-y divide-slate-100">
                    @forelse($structures as $s)
                        <div class="p-4 hover:bg-slate-50 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                            <div>
                                <h3 class="font-bold text-slate-900 text-sm">{{ $s->name }}</h3>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    {{ $s->branch->name ?? 'Campus' }} • {{ $s->schoolClass->name ?? 'All Classes' }}
                                </p>
                                <div class="flex flex-wrap gap-1.5 mt-2">
                                    @foreach($s->items as $item)
                                        <span class="inline-flex items-center text-[11px] bg-slate-100 text-slate-700 px-2 py-0.5 rounded border border-slate-200">
                                            {{ $item->feeCategory->name ?? 'Head' }}: <strong class="ml-1 font-mono">PKR {{ number_format($item->amount, 0) }}</strong>
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="text-xs text-slate-400 block uppercase">Total Head Sum</span>
                                <span class="text-base font-bold text-indigo-700 font-mono">
                                    PKR {{ number_format($s->items->sum('amount'), 2) }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center text-slate-400 text-sm">
                            {{ $isRtl ? 'کوئی فیس اسٹرکچر تشکیل نہیں دیا گیا۔' : 'No fee structures created yet.' }}
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @else
        <!-- Fee Categories View -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Create Category Form -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-4">
                <h2 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3">
                    {{ $isRtl ? 'نئی فیس کیٹیگری' : 'Create Fee Category' }}
                </h2>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Unique Code</label>
                    <input type="text" wire:model.live="categoryCode" placeholder="e.g. LAB_FEE" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg font-mono uppercase">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Category Title</label>
                    <input type="text" wire:model.live="categoryName" placeholder="e.g. Science Lab & Computing Fee" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">GL Revenue Account</label>
                    <select wire:model.live="categoryRevenueAccountId" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                        @foreach($revenueAccounts as $rev)
                            <option value="{{ $rev->id }}">{{ $rev->account_code }} - {{ $rev->account_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="pt-2">
                    <button type="button" wire:click="createCategory" class="w-full py-2.5 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg text-sm shadow transition">
                        ✓ {{ $isRtl ? 'کیٹیگری محفوظ کریں' : 'Save Category' }}
                    </button>
                </div>
            </div>

            <!-- Categories Table -->
            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-600">
                        <thead class="bg-slate-50 uppercase font-semibold text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="py-3 px-4">Code</th>
                                <th class="py-3 px-4">Title</th>
                                <th class="py-3 px-4">Default GL Revenue Account</th>
                                <th class="py-3 px-4 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($categories as $cat)
                                <tr class="hover:bg-slate-50">
                                    <td class="py-3 px-4 font-mono font-bold text-slate-900">{{ $cat->code }}</td>
                                    <td class="py-3 px-4 font-semibold text-slate-800">{{ $cat->name }}</td>
                                    <td class="py-3 px-4 text-slate-600">{{ $cat->defaultRevenueAccount->account_code ?? '—' }} {{ $cat->defaultRevenueAccount->account_name ?? '' }}</td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">ACTIVE</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
