<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <h1 class="text-2xl font-bold text-slate-900">
            {{ __('sms.staff.title') }}
        </h1>
        <p class="text-sm text-slate-500 mt-1">
            {{ __('sms.staff.subtitle') }}
        </p>
    </div>

    @if($successMessage)
        <div class="bg-emerald-50 border border-emerald-300 text-emerald-900 px-4 py-3 rounded-lg text-sm">✓ {{ $successMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="bg-rose-50 border border-rose-300 text-rose-900 px-4 py-3 rounded-lg text-sm">✕ {{ $errorMessage }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Create Staff Form -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-4">
            <h2 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-2">{{ __('sms.staff.add_staff') }}</h2>

            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.staff.full_name') }}</label>
                <input type="text" wire:model="name" placeholder="Mrs. Ayesha Tariq" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                @error('name') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.staff.email') }}</label>
                <input type="email" wire:model="email" placeholder="ayesha@school.edu.pk" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                @error('email') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.staff.phone') }}</label>
                <input type="text" wire:model="phone" placeholder="03001234567" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.staff.initial_password') }}</label>
                <input type="password" wire:model="password" placeholder="{{ __('sms.staff.password_placeholder') }}" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                @error('password') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.staff.role') }}</label>
                    <select wire:model="roleName" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                        @foreach($availableRoles as $rName)
                            <option value="{{ $rName }}">{{ ucwords(str_replace('_', ' ', $rName)) }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">{{ __('sms.staff.campus') }}</label>
                    <select wire:model="branchId" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                        @foreach($branches as $b)
                            <option value="{{ $b->id }}">{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <button type="button" wire:click="createStaff" class="w-full py-2.5 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg text-sm">
                {{ __('sms.staff.create_account') }}
            </button>
        </div>

        <!-- Staff Directory Table -->
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-start text-xs text-slate-600">
                    <thead class="bg-slate-50 uppercase font-semibold text-slate-500 border-b border-slate-200">
                        <tr>
                            <th class="py-3 px-4 text-start">{{ __('sms.staff.col_member') }}</th>
                            <th class="py-3 px-4 text-start">{{ __('sms.staff.col_campus') }}</th>
                            <th class="py-3 px-4 text-start">{{ __('sms.staff.col_assigned_role') }}</th>
                            <th class="py-3 px-4 text-start">{{ __('sms.common.status') }}</th>
                            <th class="py-3 px-4 text-center">{{ __('sms.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($staffMembers as $member)
                            <tr class="hover:bg-slate-50">
                                <td class="py-3 px-4">
                                    <div class="font-bold text-slate-900">{{ $member->name }}</div>
                                    <div class="text-[11px] text-slate-500">{{ $member->email }}</div>
                                </td>
                                <td class="py-3 px-4">{{ $member->branch->name ?? 'Main' }}</td>
                                <td class="py-3 px-4">
                                    <select wire:change="assignRole({{ $member->id }}, $event.target.value)" class="px-2 py-1 border border-slate-300 rounded text-xs font-semibold">
                                        @php $currentRole = $member->roles->first()?->name ?? 'teacher'; @endphp
                                        @foreach($availableRoles as $rName)
                                            <option value="{{ $rName }}" @selected($currentRole === $rName)>{{ ucwords(str_replace('_', ' ', $rName)) }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $member->status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                        {{ $member->status }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    @if(auth()->id() !== $member->id)
                                        <button type="button" wire:click="toggleStatus({{ $member->id }})" class="text-xs font-bold {{ $member->status === 'active' ? 'text-rose-600' : 'text-emerald-600' }} hover:underline">
                                            {{ $member->status === 'active' ? __('sms.staff.suspend') : __('sms.staff.activate') }}
                                        </button>
                                    @else
                                        <span class="text-slate-400">{{ __('sms.staff.current_user') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-8 text-center text-slate-400">{{ __('sms.staff.no_staff') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
