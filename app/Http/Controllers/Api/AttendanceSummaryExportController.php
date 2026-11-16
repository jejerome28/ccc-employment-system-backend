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
        ['from' => $from, 'to' => $to] = $request->range();
        $path = tempnam(sys_get_temp_dir(), 'summary');

        $employees = $summary->employees($from, $to);
        $through = $summary->through($from, $to);

        $exporter->write($summary->build($from, $to, $employees, $through), $path, $summary->walk($from, $to, $employees, $through));

        return response()
            ->download($path, "attendance_summary_{$from}_{$to}.xlsx", [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend();
    }
}
