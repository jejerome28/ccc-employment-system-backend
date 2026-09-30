@php
    $fields = [
        ['employee_code', 'Employee code', 'text', true],
        ['first_name', 'First name', 'text', true],
        ['last_name', 'Last name', 'text', true],
        ['email', 'Email', 'email', false],
        ['phone', 'Phone', 'text', false],
        ['position', 'Position', 'text', false],
        ['department', 'Department', 'text', false],
        ['hire_date', 'Hire date', 'date', false],
    ];
@endphp

<div class="bg-white border border-line rounded p-6 grid sm:grid-cols-2 gap-4 max-w-3xl">
    @foreach ($fields as [$name, $label, $type, $required])
        <div>
            <label for="{{ $name }}" class="block text-sm font-medium mb-1">
                {{ $label }} @unless ($required)<span class="text-muted font-normal">(optional)</span>@endunless
            </label>
            <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" @required($required)
                   value="{{ old($name, $name === 'hire_date' ? $employee->hire_date?->format('Y-m-d') : $employee->{$name}) }}"
                   class="w-full rounded border {{ $errors->has($name) ? 'border-brick' : 'border-line' }} px-3 py-2 text-sm focus:border-ink focus:outline-none focus:ring-1 focus:ring-ink">
            @error($name)
                <p class="mt-1 text-xs text-brick">{{ $message }}</p>
            @enderror
        </div>
    @endforeach

    <div>
        <label for="status" class="block text-sm font-medium mb-1">Status</label>
        <select id="status" name="status" class="w-full rounded border border-line px-3 py-2 text-sm">
            <option value="active" @selected(old('status', $employee->status) === 'active')>Active</option>
            <option value="inactive" @selected(old('status', $employee->status) === 'inactive')>Inactive</option>
        </select>
        <p class="mt-1 text-xs text-muted">Inactive staff stay in the database but leave the time clock.</p>
    </div>

    <div class="sm:col-span-2 flex items-center gap-3 pt-2">
        <button type="submit" class="rounded bg-ink px-4 py-2 text-sm font-semibold text-canvas hover:bg-ink/90">
            {{ $submitLabel }}
        </button>
        <a href="{{ route('employees.index') }}" class="text-sm text-muted underline underline-offset-2">Cancel</a>
    </div>
</div>
