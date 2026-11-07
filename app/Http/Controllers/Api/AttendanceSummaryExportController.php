<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceSummaryRequest;
use App\Services\AttendanceSummary;
use App\Services\AttendanceSummaryExporter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttendanceSummaryExportController extends Controller
{
    public function __invoke(AttendanceSummaryRequest $request, AttendanceSummary $summary, AttendanceSummaryExporter $exporter): BinaryFileResponse
    {
        ['from' => $from, 'to' => $to] = $request->validated();
        $path = tempnam(sys_get_temp_dir(), 'summary');

        $exporter->write($summary->build($from, $to), $path);

        return response()
            ->download($path, "attendance_summary_{$from}_{$to}.xlsx", [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend();
    }
}
