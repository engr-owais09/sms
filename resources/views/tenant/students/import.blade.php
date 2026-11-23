@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-900">Bulk Student &amp; Guardian Import</h1>
            <p class="text-xs text-slate-500 mt-1">Upload a CSV file with dry-run validation preview, sibling CNIC linking, and quota enforcement.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('tenant.students.import.template', ['lang' => 'en']) }}" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 rounded-lg text-xs font-bold text-slate-700">Template (EN)</a>
            <a href="{{ route('tenant.students.import.template', ['lang' => 'ur']) }}" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 rounded-lg text-xs font-bold text-slate-700">Template (UR)</a>
        </div>
    </div>

    @if(session('import_result'))
        @php $res = session('import_result'); @endphp
        <div class="bg-white rounded-xl border border-indigo-200 p-5 shadow-sm space-y-2 text-xs">
            <div class="font-bold text-indigo-900">
                Total: {{ $res['total'] }} | Valid: {{ $res['valid'] }} | Skipped (duplicates): {{ $res['skipped'] }} | Imported: {{ $res['imported'] }}
            </div>
            @if(!empty($res['errors']))
                <ul class="text-rose-700 list-disc ps-5 space-y-0.5">
                    @foreach($res['errors'] as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
        <form method="POST" action="{{ route('tenant.students.import.store') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">CSV File (max 2 MB)</label>
                <input type="file" name="csv_file" accept=".csv,.txt" required class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg">
                @error('csv_file') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-700">
                <input type="checkbox" name="dry_run" value="1" checked class="rounded text-indigo-600"> Dry-run validation preview only (do not write to database)
            </label>
            <div>
                <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-lg">
                    Process CSV Import
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
