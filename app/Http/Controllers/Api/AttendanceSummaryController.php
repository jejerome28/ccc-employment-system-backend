<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceSummaryRequest;
use App\Http\Responses\ApiResponse;
use App\Services\AttendanceSummary;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

class AttendanceSummaryController extends Controller
{
    public function __invoke(AttendanceSummaryRequest $request, AttendanceSummary $summary): JsonResponse
    {
        ['from' => $from, 'to' => $to] = $request->range();

        $data = $summary->build($from, $to, $summary->employees($from, $to, $request->validated('q')));
        if (! in_array($request->user()->role, [Role::Admin, Role::Hr], true)) {
            $data['needs_check'] = [];
        }
        $p = (int) $request->validated('page', 1);
        $page = new LengthAwarePaginator(array_slice($data['rows'], ($p - 1) * 25, 25), count($data['rows']), 25, $p);

        return ApiResponse::success('OK', [...$data, 'rows' => $page->items(), 'meta' => ApiResponse::meta($page)]);
    }
}
