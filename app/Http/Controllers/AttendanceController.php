<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $date = $request->query('date', now()->toDateString());

        $attendances = Attendance::query()
            ->with('employee')
            ->whereDate('work_date', $date)
            ->when($request->query('q'), function ($query, $term) {
                $query->whereHas('employee', fn ($q) => $q->search($term));
            })
            ->get()
            ->sortBy(fn ($a) => $a->employee->last_name);

        return view('attendance.index', [
            'attendances' => $attendances,
            'date' => $date,
            'q' => $request->query('q'),
            'employees' => Employee::active()->orderBy('last_name')->get(),
            'totalMinutes' => $attendances->sum(fn ($a) => $a->worked_minutes ?? 0),
        ]);
    }

    /**
     * Clock the employee in for today. Ignored if they already have a time in.
     */
    public function timeIn(Employee $employee): RedirectResponse
    {
        $attendance = Attendance::firstOrNew([
            'employee_id' => $employee->id,
            'work_date' => now()->toDateString(),
        ]);

        if ($attendance->time_in) {
            return back()->with('error', "{$employee->full_name} already timed in at "
                . Attendance::formatClock($attendance->time_in) . '.');
        }

        $attendance->time_in = now()->format('H:i:s');
        $attendance->save();

        return back()->with('status', "{$employee->full_name} timed in at "
            . Attendance::formatClock($attendance->time_in) . '.');
    }

    /**
     * Clock the employee out for today.
     */
    public function timeOut(Employee $employee): RedirectResponse
    {
        $attendance = Attendance::where('employee_id', $employee->id)
            ->whereDate('work_date', now()->toDateString())
            ->first();

        if (! $attendance || ! $attendance->time_in) {
            return back()->with('error', "{$employee->full_name} has not timed in today.");
        }

        if ($attendance->time_out) {
            return back()->with('error', "{$employee->full_name} already timed out at "
                . Attendance::formatClock($attendance->time_out) . '.');
        }

        $attendance->time_out = now()->format('H:i:s');
        $attendance->save();

        return back()->with('status', "{$employee->full_name} timed out at "
            . Attendance::formatClock($attendance->time_out)
            . " ({$attendance->duration_for_humans}).");
    }

    /**
     * Manual entry, for corrections and for days the clock was missed.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'work_date' => ['required', 'date', 'before_or_equal:today'],
            'time_in' => ['nullable', 'date_format:H:i'],
            'time_out' => ['nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $attendance = Attendance::updateOrCreate(
            ['employee_id' => $data['employee_id'], 'work_date' => $data['work_date']],
            [
                'time_in' => $data['time_in'] ?? null,
                'time_out' => $data['time_out'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]
        );

        return redirect()
            ->route('attendance.index', ['date' => $data['work_date']])
            ->with('status', "Record saved for {$attendance->employee->full_name}.");
    }

    public function edit(Attendance $attendance): View
    {
        return view('attendance.edit', ['attendance' => $attendance->load('employee')]);
    }

    public function update(Request $request, Attendance $attendance): RedirectResponse
    {
        $data = $request->validate([
            'time_in' => ['nullable', 'date_format:H:i'],
            'time_out' => ['nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $attendance->update($data);

        return redirect()
            ->route('attendance.index', ['date' => $attendance->work_date->toDateString()])
            ->with('status', 'Attendance record updated.');
    }

    public function destroy(Attendance $attendance): RedirectResponse
    {
        $date = $attendance->work_date->toDateString();
        $attendance->delete();

        return redirect()
            ->route('attendance.index', ['date' => $date])
            ->with('status', 'Attendance record deleted.');
    }
}
