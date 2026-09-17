@extends('layouts.app')

@section('title', 'Time records')
@section('heading', 'Time records')
@section('subheading', \Carbon\Carbon::parse($date)->format('l, d F Y'))

@section('content')

    <form method="GET" class="mb-5 flex flex-wrap items-center gap-2">
        <input type="date" name="date" value="{{ $date }}"
               class="rounded border border-line bg-white px-3 py-2 text-sm">
        <input type="search" name="q" value="{{ $q }}" placeholder="Filter by employee"
               class="flex-1 min-w-48 rounded border border-line bg-white px-3 py-2 text-sm">
        <button class="rounded border border-ink px-4 py-2 text-sm font-semibold hover:bg-ink hover:text-canvas">Show</button>
        <a href="{{ route('attendance.index') }}" class="px-3 py-2 text-sm text-muted underline underline-offset-2">Today</a>
    </form>

    <div class="bg-white border border-line rounded overflow-x-auto mb-8">
        <table class="w-full text-sm">
            <thead class="text-left text-xs text-muted border-b border-line">
                <tr>
                    <th class="px-4 py-2 font-medium">Employee</th>
                    <th class="px-4 py-2 font-medium">Time in</th>
                    <th class="px-4 py-2 font-medium">Time out</th>
                    <th class="px-4 py-2 font-medium">Worked</th>
                    <th class="px-4 py-2 font-medium">Notes</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($attendances as $attendance)
                    <tr>
                        <td class="px-4 py-3">
                            <a href="{{ route('employees.show', $attendance->employee) }}" class="font-medium hover:underline underline-offset-2">
                                {{ $attendance->employee->full_name }}
                            </a>
                            <p class="text-xs text-muted">{{ $attendance->employee->employee_code }}</p>
                        </td>
                        <td class="px-4 py-3 tnum">{{ \App\Models\Attendance::formatClock($attendance->time_in) }}</td>
                        <td class="px-4 py-3 tnum">{{ \App\Models\Attendance::formatClock($attendance->time_out) }}</td>
                        <td class="px-4 py-3 tnum">{{ $attendance->duration_for_humans }}</td>
                        <td class="px-4 py-3 text-xs text-muted">{{ $attendance->notes ?: '—' }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('attendance.edit', $attendance) }}" class="text-xs font-semibold underline underline-offset-2">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-sm text-muted">
                            Nobody has clocked in on this date yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if ($attendances->isNotEmpty())
                <tfoot class="border-t border-line">
                    <tr>
                        <td class="px-4 py-3 text-xs text-muted" colspan="3">{{ $attendances->count() }} records</td>
                        <td class="px-4 py-3 tnum font-semibold" colspan="3">
                            {{ intdiv($totalMinutes, 60) }}h {{ str_pad($totalMinutes % 60, 2, '0', STR_PAD_LEFT) }}m total
                        </td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    <h2 class="font-semibold mb-2">Add or correct a record</h2>
    <p class="text-sm text-muted mb-3">Use this when someone forgot to clock in, or to fix a wrong time. Saving overwrites the record for that employee and date.</p>

    <form method="POST" action="{{ route('attendance.store') }}"
          class="bg-white border border-line rounded p-5 grid sm:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
        @csrf
        <div class="lg:col-span-2">
            <label for="employee_id" class="block text-sm font-medium mb-1">Employee</label>
            <select id="employee_id" name="employee_id" required class="w-full rounded border border-line px-3 py-2 text-sm">
                @foreach ($employees as $employee)
                    <option value="{{ $employee->id }}" @selected(old('employee_id') == $employee->id)>
                        {{ $employee->full_name }} ({{ $employee->employee_code }})
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="work_date" class="block text-sm font-medium mb-1">Date</label>
            <input id="work_date" name="work_date" type="date" required value="{{ old('work_date', $date) }}"
                   class="w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="time_in" class="block text-sm font-medium mb-1">Time in</label>
            <input id="time_in" name="time_in" type="time" value="{{ old('time_in') }}"
                   class="w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <label for="time_out" class="block text-sm font-medium mb-1">Time out</label>
            <input id="time_out" name="time_out" type="time" value="{{ old('time_out') }}"
                   class="w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div class="lg:col-span-4">
            <label for="notes" class="block text-sm font-medium mb-1">Notes <span class="text-muted font-normal">(optional)</span></label>
            <input id="notes" name="notes" type="text" maxlength="255" value="{{ old('notes') }}"
                   placeholder="Half day, field work, forgot to clock out"
                   class="w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <div>
            <button class="w-full rounded bg-ink px-4 py-2 text-sm font-semibold text-canvas hover:bg-ink/90">Save record</button>
        </div>
    </form>
@endsection
