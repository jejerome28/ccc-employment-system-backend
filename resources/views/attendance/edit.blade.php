@extends('layouts.app')

@section('title', 'Edit time record')
@section('heading', 'Edit time record')
@section('subheading', $attendance->employee->full_name . ' · ' . $attendance->work_date->format('l, d F Y'))

@section('content')
    <form method="POST" action="{{ route('attendance.update', $attendance) }}" class="max-w-xl">
        @csrf
        @method('PUT')

        <div class="bg-white border border-line rounded p-6 grid sm:grid-cols-2 gap-4">
            <div>
                <label for="time_in" class="block text-sm font-medium mb-1">Time in</label>
                <input id="time_in" name="time_in" type="time"
                       value="{{ old('time_in', $attendance->time_in ? \Carbon\Carbon::parse($attendance->time_in)->format('H:i') : '') }}"
                       class="w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="time_out" class="block text-sm font-medium mb-1">Time out</label>
                <input id="time_out" name="time_out" type="time"
                       value="{{ old('time_out', $attendance->time_out ? \Carbon\Carbon::parse($attendance->time_out)->format('H:i') : '') }}"
                       class="w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div class="sm:col-span-2">
                <label for="notes" class="block text-sm font-medium mb-1">Notes <span class="text-muted font-normal">(optional)</span></label>
                <input id="notes" name="notes" type="text" maxlength="255" value="{{ old('notes', $attendance->notes) }}"
                       class="w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div class="sm:col-span-2 flex items-center gap-3 pt-1">
                <button class="rounded bg-ink px-4 py-2 text-sm font-semibold text-canvas hover:bg-ink/90">Save changes</button>
                <a href="{{ route('attendance.index', ['date' => $attendance->work_date->toDateString()]) }}"
                   class="text-sm text-muted underline underline-offset-2">Cancel</a>
            </div>
        </div>
    </form>

    <form method="POST" action="{{ route('attendance.destroy', $attendance) }}" class="mt-6 max-w-xl"
          onsubmit="return confirm('Delete this time record?')">
        @csrf
        @method('DELETE')
        <button class="rounded border border-brick px-4 py-2 text-sm font-semibold text-brick hover:bg-brick hover:text-white">
            Delete this record
        </button>
    </form>
@endsection
