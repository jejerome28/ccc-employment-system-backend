@extends('layouts.app')

@section('title', 'Today')
@section('heading', 'Today')
@section('subheading', $today->format('l, d F Y'))

@section('actions')
    <a href="{{ route('attendance.index') }}"
       class="inline-block rounded border border-ink px-4 py-2 text-sm font-semibold hover:bg-ink hover:text-canvas">
        Open time records
    </a>
@endsection

@section('content')

    @php
        $cards = [
            ['label' => 'On the clock now', 'value' => $stats['clocked_in'], 'tone' => 'text-clay'],
            ['label' => 'Finished for the day', 'value' => $stats['completed'], 'tone' => 'text-moss'],
            ['label' => 'Not yet in', 'value' => $stats['not_in'], 'tone' => 'text-muted'],
            ['label' => 'Hours logged today', 'value' => number_format($hoursToday, 2), 'tone' => 'text-ink'],
        ];
    @endphp

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-px bg-line border border-line rounded overflow-hidden mb-8">
        @foreach ($cards as $card)
            <div class="bg-white px-4 py-4">
                <p class="text-3xl font-bold tnum {{ $card['tone'] }}">{{ $card['value'] }}</p>
                <p class="text-xs text-muted mt-1">{{ $card['label'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="bg-white border border-line rounded overflow-hidden">
        <div class="px-4 py-3 border-b border-line flex items-center justify-between">
            <h2 class="font-semibold">Time clock</h2>
            <span class="text-xs text-muted">{{ $stats['active'] }} active employees</span>
        </div>

        @if ($employees->isEmpty())
            <div class="px-4 py-10 text-center">
                <p class="text-sm text-muted mb-3">No active employees yet.</p>
                <a href="{{ route('employees.create') }}" class="text-sm font-semibold underline underline-offset-2">Add your first employee</a>
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="text-left text-xs text-muted border-b border-line">
                    <tr>
                        <th class="px-4 py-2 font-medium">Employee</th>
                        <th class="px-4 py-2 font-medium">Time in</th>
                        <th class="px-4 py-2 font-medium">Time out</th>
                        <th class="px-4 py-2 font-medium">Worked</th>
                        <th class="px-4 py-2 font-medium text-right">Clock</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($employees as $employee)
                        @php $log = $employee->todayAttendance; @endphp
                        <tr>
                            <td class="px-4 py-3">
                                <a href="{{ route('employees.show', $employee) }}" class="font-medium hover:underline underline-offset-2">
                                    {{ $employee->full_name }}
                                </a>
                                <p class="text-xs text-muted">{{ $employee->employee_code }} · {{ $employee->position ?: 'No position set' }}</p>
                            </td>
                            <td class="px-4 py-3 tnum">{{ \App\Models\Attendance::formatClock($log?->time_in) }}</td>
                            <td class="px-4 py-3 tnum">{{ \App\Models\Attendance::formatClock($log?->time_out) }}</td>
                            <td class="px-4 py-3 tnum {{ $log && $log->time_in && ! $log->time_out ? 'text-clay' : '' }}">
                                {{ $log?->duration_for_humans ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    @if (! $log?->time_in)
                                        <form method="POST" action="{{ route('attendance.time-in', $employee) }}">
                                            @csrf
                                            <button class="rounded bg-moss px-3 py-1.5 text-xs font-semibold text-white hover:bg-moss/90">
                                                Time in
                                            </button>
                                        </form>
                                    @elseif (! $log?->time_out)
                                        <form method="POST" action="{{ route('attendance.time-out', $employee) }}">
                                            @csrf
                                            <button class="rounded bg-ink px-3 py-1.5 text-xs font-semibold text-canvas hover:bg-ink/90">
                                                Time out
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-xs text-muted">Done for today</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
