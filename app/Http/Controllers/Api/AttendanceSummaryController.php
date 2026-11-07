<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceSummaryRequest;
use App\Http\Responses\ApiResponse;
use App\Services\AttendanceSummary;
use Illuminate\Http\JsonResponse;

class AttendanceSummaryController extends Controller
{
    public function __invoke(AttendanceSummaryRequest $request, AttendanceSummary $summary): JsonResponse
    {
        ['from' => $from, 'to' => $to] = $request->validated();

        return ApiResponse::success('OK', $summary->build($from, $to));
    }
}
