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
        $path = $request->file('file')->getRealPath();

        if (! mb_check_encoding(file_get_contents($path), 'UTF-8')) {
            return ApiResponse::error('File is not UTF-8.', 422);
        }

        ['rows' => $rows, 'errors' => $errors] = $importer->parse($path);

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
