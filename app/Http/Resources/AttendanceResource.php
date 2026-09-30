<?php

namespace App\Http\Resources;

use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Attendance
 */
class AttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'work_date' => $this->work_date,
            'clock_in_at' => $this->clock_in_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'clock_out_at' => $this->clock_out_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'notes' => $this->notes,
            'worked_minutes' => $this->worked_minutes,
            'timetable' => $this->timetable,
            'status' => $this->status,
            'work_minutes' => $this->work_minutes,
            'ot_minutes' => $this->ot_minutes,
            'attended_minutes' => $this->attended_minutes,
            'late_minutes' => $this->late_minutes,
            'early_minutes' => $this->early_minutes,
            'absent_minutes' => $this->absent_minutes,
            'leave_minutes' => $this->leave_minutes,
            'employee' => $this->whenLoaded('employee', fn ($e) => new EmployeeResource($e)),
        ];
    }
}
