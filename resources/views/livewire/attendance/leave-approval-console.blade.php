<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">
                {{ $isRtl ? 'طلباء کی رخصت کی منظوری' : 'Student Leave Approval Console' }}
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                {{ $isRtl ? 'رخصت کی درخواستوں کا جائزہ لیں اور حاضری خودکار اپڈیٹ کریں' : 'Review leave requests with automatic excused attendance synchronization' }}
            </p>
        </div>

        <div>
            <select wire:model.live="statusFilter" class="px-3 py-2 text-xs font-semibold border border-slate-300 rounded-lg">
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
                <option value="all">All Requests</option>
            </select>
        </div>
    </div>

    @if($successMessage)
        <div class="bg-emerald-50 border border-emerald-300 text-emerald-900 px-4 py-3 rounded-lg text-sm">✓ {{ $successMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="bg-rose-50 border border-rose-300 text-rose-900 px-4 py-3 rounded-lg text-sm">✕ {{ $errorMessage }}</div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50 uppercase font-semibold text-slate-500 border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-4">Student</th>
                        <th class="py-3 px-4">Type</th>
                        <th class="py-3 px-4">Date Range</th>
                        <th class="py-3 px-4">Reason</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($leaves as $leave)
                        <tr class="hover:bg-slate-50">
                            <td class="py-3 px-4 font-semibold text-slate-900">
                                {{ $leave->student->first_name ?? '' }} {{ $leave->student->last_name ?? '' }}
                                <span class="block text-[10px] font-mono text-slate-500">{{ $leave->student->admission_number ?? '' }}</span>
                            </td>
                            <td class="py-3 px-4 uppercase font-semibold">{{ $leave->leave_type }}</td>
                            <td class="py-3 px-4 font-mono">{{ $leave->start_date->format('Y-m-d') }} → {{ $leave->end_date->format('Y-m-d') }}</td>
                            <td class="py-3 px-4">{{ $leave->reason }}</td>
                            <td class="py-3 px-4">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $leave->status === 'approved' ? 'bg-emerald-100 text-emerald-800' : ($leave->status === 'rejected' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800') }}">
                                    {{ $leave->status }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center space-x-1">
                                @if($leave->status === 'pending')
                                    <button type="button" wire:click="approve('{{ $leave->id }}')" class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-[11px] font-bold">
                                        ✓ Approve
                                    </button>
                                    <button type="button" wire:click="reject('{{ $leave->id }}')" class="px-2.5 py-1 bg-rose-600 hover:bg-rose-700 text-white rounded text-[11px] font-bold">
                                        ✕ Reject
                                    </button>
                                @else
                                    <span class="text-slate-400">Reviewed</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-400">No leave requests found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
