@extends('layouts.app')

@section('title', 'Edit employee')
@section('heading', 'Edit ' . $employee->full_name)
@section('subheading', $employee->employee_code)

@section('content')
    <form method="POST" action="{{ route('employees.update', $employee) }}">
        @csrf
        @method('PUT')
        @include('employees._form', ['submitLabel' => 'Save changes'])
    </form>

    <form method="POST" action="{{ route('employees.destroy', $employee) }}" class="mt-8 max-w-3xl"
          onsubmit="return confirm('Delete {{ $employee->full_name }} and every attendance record for them? This cannot be undone.')">
        @csrf
        @method('DELETE')
        <div class="border border-brick/30 bg-brick/5 rounded p-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-brick">Delete this employee</p>
                <p class="text-xs text-muted">Their attendance history is removed too. Set the status to inactive instead if you need the records.</p>
            </div>
            <button class="rounded border border-brick px-4 py-2 text-sm font-semibold text-brick hover:bg-brick hover:text-white">
                Delete
            </button>
        </div>
    </form>
@endsection
