@extends('layouts.app')

@section('title', 'Employees')
@section('heading', 'Employees')
@section('subheading', $employees->total() . ' records')

@section('actions')
    <a href="{{ route('employees.create') }}"
       class="inline-block rounded bg-ink px-4 py-2 text-sm font-semibold text-canvas hover:bg-ink/90">
        Add employee
    </a>
@endsection

@section('content')
    <form method="GET" class="mb-5 flex flex-wrap gap-2">
        <input type="search" name="q" value="{{ $q }}" placeholder="Search name, code, department"
               class="flex-1 min-w-52 rounded border border-line bg-white px-3 py-2 text-sm focus:border-ink focus:outline-none focus:ring-1 focus:ring-ink">
        <select name="status" class="rounded border border-line bg-white px-3 py-2 text-sm">
            <option value="">All statuses</option>
            <option value="active" @selected($status === 'active')>Active</option>
            <option value="inactive" @selected($status === 'inactive')>Inactive</option>
        </select>
        <button class="rounded border border-ink px-4 py-2 text-sm font-semibold hover:bg-ink hover:text-canvas">Search</button>
        @if ($q || $status)
            <a href="{{ route('employees.index') }}" class="px-3 py-2 text-sm text-muted underline underline-offset-2">Clear</a>
        @endif
    </form>

    <div class="bg-white border border-line rounded overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-left text-xs text-muted border-b border-line">
                <tr>
                    <th class="px-4 py-2 font-medium">Code</th>
                    <th class="px-4 py-2 font-medium">Name</th>
                    <th class="px-4 py-2 font-medium">Position</th>
                    <th class="px-4 py-2 font-medium">Department</th>
                    <th class="px-4 py-2 font-medium">Today</th>
                    <th class="px-4 py-2 font-medium">Status</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($employees as $employee)
                    <tr>
                        <td class="px-4 py-3 tnum text-muted">{{ $employee->employee_code }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('employees.show', $employee) }}" class="font-medium hover:underline underline-offset-2">
                                {{ $employee->full_name }}
                            </a>
                        </td>
                        <td class="px-4 py-3">{{ $employee->position ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $employee->department ?: '—' }}</td>
                        <td class="px-4 py-3 tnum text-xs">
                            {{ \App\Models\Attendance::formatClock($employee->todayAttendance?->time_in) }}
                            &ndash;
                            {{ \App\Models\Attendance::formatClock($employee->todayAttendance?->time_out) }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium
                                {{ $employee->status === 'active' ? 'bg-moss/10 text-moss' : 'bg-ink/10 text-muted' }}">
                                {{ ucfirst($employee->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('employees.edit', $employee) }}" class="text-xs font-semibold underline underline-offset-2">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-sm text-muted">
                            No employees match this search.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $employees->links() }}</div>
@endsection
