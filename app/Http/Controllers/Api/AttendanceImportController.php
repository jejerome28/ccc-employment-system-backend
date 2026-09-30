<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceImportRequest;
use App\Http\Responses\ApiResponse;
use App\Services\BiometricReportImporter;
use Illuminate\Http\JsonResponse;

class AttendanceImportController extends Controller
{
    public function __invoke(AttendanceImportRequest $request, BiometricReportImporter $importer): JsonResponse
    {
        ['rows' => $rows, 'errors' => $errors] = $importer->parse($request->file('file')->getRealPath());

        if ($errors) {
            return ApiResponse::error('The file has errors. Nothing was imported.', 422, ['errors' => $errors]);
        }

        if (! $rows) {
            return ApiResponse::error('No attendance rows found.', 422);
        }

        $result = $importer->save($rows);

        return ApiResponse::success('Imported '.($result['created'] + $result['updated']).' rows.', $result);
    }
}
