<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\EmployeeRosterImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeImportController extends Controller
{
    public function __invoke(Request $request, EmployeeRosterImporter $importer): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'extensions:xlsx', 'max:5120']]);

        ['rows' => $rows, 'errors' => $errors] = $importer->parse($request->file('file')->getRealPath());

        if ($errors) {
            return ApiResponse::error('The file has errors. Nothing was imported.', 422, ['errors' => $errors]);
        }
        if (! $rows) {
            return ApiResponse::error('No employees found in the Roster sheet.', 422);
        }

        $result = $importer->save($rows);

        return ApiResponse::success('Saved '.array_sum($result).' employees.', $result);
    }
}
