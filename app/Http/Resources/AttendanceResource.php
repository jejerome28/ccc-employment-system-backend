<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Attendance
 */
class AttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'work_date' => $this->work_date->toDateString(),
            'time_in' => $this->time_in,
            'time_out' => $this->time_out,
            'notes' => $this->notes,
            'worked_minutes' => $this->worked_minutes,
            'employee' => $this->whenLoaded('employee', fn ($e) => new EmployeeResource($e)),
        ];
    }
}
