@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <h1 class="text-xl font-bold text-slate-900">Campus Notice Board</h1>
        <p class="text-xs text-slate-500 mt-1">Publish bilingual notices to parents, students, and staff.</p>
    </div>

    @if(session('status'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg text-sm">✓ {{ session('status') }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <form method="POST" action="{{ route('tenant.notices.store') }}" class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm space-y-3">
            @csrf
            <h2 class="text-sm font-bold text-slate-900">Publish Notice</h2>
            <input type="text" name="title" required placeholder="Title (English)" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg">
            <input type="text" name="title_ur" placeholder="Title (Urdu optional)" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg">
            <textarea name="body" required rows="3" placeholder="Message (English)" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg"></textarea>
            <textarea name="body_ur" rows="2" placeholder="Message (Urdu optional)" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg"></textarea>
            <select name="audience" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg">
                <option value="all">All</option>
                <option value="parents">Parents</option>
                <option value="students">Students</option>
                <option value="staff">Staff</option>
            </select>
            <button type="submit" class="w-full py-2.5 bg-indigo-600 text-white text-xs font-bold rounded-lg">Publish Notice</button>
        </form>

        <div class="lg:col-span-2 space-y-3">
            @forelse($notices as $notice)
                <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm space-y-1">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-sm text-slate-900">{{ $notice->title }}</span>
                        <span class="px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 text-[10px] font-bold uppercase">{{ $notice->audience }}</span>
                    </div>
                    <p class="text-xs text-slate-600">{{ $notice->body }}</p>
                </div>
            @empty
                <div class="bg-white rounded-xl border border-slate-200 p-6 text-center text-xs text-slate-400">No notices published yet.</div>
            @endforelse
            {{ $notices->links() }}
        </div>
    </div>
</div>
@endsection
