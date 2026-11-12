<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceSummaryRequest;
use App\Services\AttendanceSummary;
use App\Services\CraReportExporter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CraReportController extends Controller
{
    public function __invoke(AttendanceSummaryRequest $request, AttendanceSummary $summary, CraReportExporter $exporter): BinaryFileResponse
    {
        ['from' => $from, 'to' => $to] = $request->validated();
        $path = tempnam(sys_get_temp_dir(), 'cra');

        $employees = $summary->employees($from, $to);
        $names = $employees->mapWithKeys(fn ($e) => [$e->id => mb_strtoupper("{$e->last_name}, {$e->first_name}")])->all();

        $exporter->write($summary->build($from, $to, $employees, $summary->through($from, $to)), $path, $names);

        return response()
            ->download($path, 'CRA '.$exporter->periodText($from, $to)['sheet'].'.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend();
    }
}
