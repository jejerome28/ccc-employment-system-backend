@extends('layouts.app')

@section('title', $employee->full_name)
@section('heading', $employee->full_name)
@section('subheading', $employee->employee_code . ' · ' . ($employee->position ?: 'No position set'))

@section('actions')
    <a href="{{ route('employees.edit', $employee) }}"
       class="inline-block rounded border border-ink px-4 py-2 text-sm font-semibold hover:bg-ink hover:text-canvas">
        Edit details
    </a>
@endsection

@section('content')
    <div class="grid lg:grid-cols-3 gap-6">

        <div class="bg-white border border-line rounded p-5 text-sm space-y-3">
            @php
                $details = [
                    'Department' => $employee->department ?: '—',
                    'Email' => $employee->email ?: '—',
                    'Phone' => $employee->phone ?: '—',
                    'Hired' => $employee->hire_date?->format('d M Y') ?: '—',
                    'Status' => ucfirst($employee->status),
                ];
            @endphp
            @foreach ($details as $label => $value)
                <div class="flex justify-between gap-4 border-b border-line pb-2 last:border-0 last:pb-0">
                    <span class="text-muted">{{ $label }}</span>
                    <span class="text-right font-medium">{{ $value }}</span>
                </div>
            @endforeach
        </div>

        <div class="lg:col-span-2 space-y-4">
            <form method="GET" class="flex flex-wrap items-center gap-2">
                <label for="month" class="text-sm text-muted">Month</label>
                <input type="month" id="month" name="month" value="{{ $month }}"
                       class="rounded border border-line bg-white px-3 py-2 text-sm">
                <button class="rounded border border-ink px-4 py-2 text-sm font-semibold hover:bg-ink hover:text-canvas">Show</button>
                <span class="ml-auto text-sm text-muted tnum">
                    {{ $daysPresent }} days ·
                    <strong class="text-ink">{{ intdiv($totalMinutes, 60) }}h {{ str_pad($totalMinutes % 60, 2, '0', STR_PAD_LEFT) }}m</strong> total
                </span>
            </form>

            <div class="bg-white border border-line rounded overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs text-muted border-b border-line">
                        <tr>
                            <th class="px-4 py-2 font-medium">Date</th>
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
                                <td class="px-4 py-3 tnum">{{ $attendance->work_date->format('D, d M') }}</td>
                                <td class="px-4 py-3 tnum">{{ \App\Models\Attendance::formatClock($attendance->time_in) }}</td>
                                <td class="px-4 py-3 tnum">{{ \App\Models\Attendance::formatClock($attendance->time_out) }}</td>
                                <td class="px-4 py-3 tnum">{{ $attendance->duration_for_humans }}</td>
                                <td class="px-4 py-3 text-muted text-xs">{{ $attendance->notes ?: '—' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('attendance.edit', $attendance) }}" class="text-xs font-semibold underline underline-offset-2">Edit</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-muted">
                                    No time records for this month.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
