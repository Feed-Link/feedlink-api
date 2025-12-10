<?php

namespace App\Modules\Core\Traits;

use Illuminate\Http\JsonResponse;

trait HasApiResponse
{

    public function success(string $message, ?int $statusCode = 200, $data = null): JsonResponse
    {
        return response()->json([
            "statusCode" => $statusCode,
            "message" => $message,
            "data" => $data
        ]);
    }

    public function error(string $message, ?int $statusCode = 400, $data = null): JsonResponse
    {
        return response()->json([
            "statusCode" => $statusCode,
            "message" => $message,
            "data" => $data
        ]);
    }
}
