@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <h1 class="text-2xl font-bold text-slate-900">Tenant Security &amp; Audit Log</h1>
        <p class="text-sm text-slate-500 mt-1">Immutable record of authentication, role changes, refunds, and document events for {{ $tenant->name }}.</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-start text-xs text-slate-600">
                <thead class="bg-slate-50 uppercase font-semibold text-slate-500 border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-4 text-start">Timestamp</th>
                        <th class="py-3 px-4 text-start">Event</th>
                        <th class="py-3 px-4 text-start">Actor</th>
                        <th class="py-3 px-4 text-start">Target</th>
                        <th class="py-3 px-4 text-start">IP Address</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($logs as $log)
                        <tr>
                            <td class="py-3 px-4 font-mono">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                            <td class="py-3 px-4 font-bold text-indigo-700">{{ $log->event }}</td>
                            <td class="py-3 px-4">{{ $log->actor?->email ?? ('User #' . ($log->actor_user_id ?? 'system')) }}</td>
                            <td class="py-3 px-4 font-mono">{{ class_basename((string) $log->auditable_type) }} {{ $log->auditable_id }}</td>
                            <td class="py-3 px-4 font-mono">{{ $log->ip_address ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-slate-400">No audit events recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-200">
            {{ $logs->links() }}
        </div>
    </div>
</div>
@endsection
