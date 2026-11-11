@extends('layouts.app')

@section('content')
<div class="py-4">
    <div class="mb-4">
        <a href="{{ route('tenant.students.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-indigo-600 transition-colors">
            <span>&larr;</span>
            <span>Back to Student Directory</span>
        </a>
    </div>
    <livewire:student-admission-form />
</div>
@endsection

