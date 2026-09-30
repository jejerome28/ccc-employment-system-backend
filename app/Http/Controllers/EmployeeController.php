<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $employees = Employee::query()
            ->search($request->query('q'))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->with('todayAttendance')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(12)
            ->withQueryString();

        return view('employees.index', [
            'employees' => $employees,
            'q' => $request->query('q'),
            'status' => $request->query('status'),
        ]);
    }

    public function create(): View
    {
        return view('employees.create', ['employee' => new Employee(['status' => 'active'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $employee = Employee::create($this->validated($request));

        return redirect()
            ->route('employees.show', $employee)
            ->with('status', "{$employee->full_name} was added.");
    }

    public function show(Request $request, Employee $employee): View
    {
        $month = $request->query('month', now()->format('Y-m'));
        [$year, $monthNumber] = array_pad(explode('-', $month), 2, now()->month);

        $attendances = $employee->attendances()
            ->whereYear('work_date', (int) $year)
            ->whereMonth('work_date', (int) $monthNumber)
            ->orderByDesc('work_date')
            ->get();

        return view('employees.show', [
            'employee' => $employee,
            'attendances' => $attendances,
            'month' => $month,
            'totalMinutes' => $attendances->sum(fn ($a) => $a->worked_minutes ?? 0),
            'daysPresent' => $attendances->whereNotNull('time_in')->count(),
        ]);
    }

    public function edit(Employee $employee): View
    {
        return view('employees.edit', ['employee' => $employee]);
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $employee->update($this->validated($request, $employee));

        return redirect()
            ->route('employees.show', $employee)
            ->with('status', 'Employee details saved.');
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        $name = $employee->full_name;
        $employee->delete();

        return redirect()
            ->route('employees.index')
            ->with('status', "{$name} and their attendance records were deleted.");
    }

    private function validated(Request $request, ?Employee $employee = null): array
    {
        return $request->validate([
            'employee_code' => [
                'required', 'string', 'max:30',
                Rule::unique('employees')->ignore($employee?->id),
            ],
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'email' => [
                'nullable', 'email', 'max:255',
                Rule::unique('employees')->ignore($employee?->id),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'position' => ['nullable', 'string', 'max:80'],
            'department' => ['nullable', 'string', 'max:80'],
            'hire_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
    }
}
