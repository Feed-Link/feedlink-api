<?php

namespace App\Modules\Core\Traits;

use Illuminate\Http\JsonResponse;
use Exception;

trait HasApiResponse
{

    public function success(string $message, ?int $statusCode = 200, $data = null): JsonResponse
    {
        return response()->json([
            "status_code" => $statusCode,
            "message" => $message,
            "data" => $data
        ]);
    }

    public function handleException(Exception $exception): JsonResponse
    {
        return response()->json([
            "status_code" => $exception->getCode() ?? 500,
            "message" => $exception->getMessage() ?? "",
            "data" => null
        ]);
    }
}
