<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceExportRequest;
use App\Services\AttendanceReportExporter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttendanceExportController extends Controller
{
    public function __invoke(AttendanceExportRequest $request, AttendanceReportExporter $exporter): BinaryFileResponse
    {
        ['from' => $from, 'to' => $to] = $request->validated();
        $path = tempnam(sys_get_temp_dir(), 'attendance');

        $exporter->write($from, $to, $path);

        return response()
            ->download($path, "attendance_{$from}_{$to}.xlsx", [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend();
    }
}
