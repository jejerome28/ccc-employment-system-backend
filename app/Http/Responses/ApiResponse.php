<?php

namespace App\Http\Responses;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success(string $message, mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data], $status);
    }

    public static function error(string $message, int $status, mixed $data = null): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message, 'data' => $data], $status);
    }

    /** @return array{current_page:int,last_page:int,per_page:int,total:int} */
    public static function meta(LengthAwarePaginator $p): array
    {
        return [
            'current_page' => $p->currentPage(),
            'last_page' => $p->lastPage(),
            'per_page' => $p->perPage(),
            'total' => $p->total(),
        ];
    }
}
