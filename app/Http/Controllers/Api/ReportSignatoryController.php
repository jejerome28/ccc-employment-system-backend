<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportSignatoryRequest;
use App\Http\Responses\ApiResponse;
use App\Models\ReportSignatory;
use Illuminate\Http\JsonResponse;

class ReportSignatoryController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success('OK', [
            'signatories' => ReportSignatory::ordered()->map->only(['id', 'position', 'label', 'name', 'title'])->values(),
        ]);
    }

    public function update(ReportSignatoryRequest $request, ReportSignatory $signatory): JsonResponse
    {
        $signatory->update($request->validated());

        return ApiResponse::success('Signatory updated.', [
            'signatory' => $signatory->only(['id', 'position', 'label', 'name', 'title']),
        ]);
    }
}
