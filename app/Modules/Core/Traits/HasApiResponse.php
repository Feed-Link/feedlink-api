<?php

namespace App\Modules\Core\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\AbstractPaginator;
use Exception;

trait HasApiResponse
{

    public function success(string $message, ?int $statusCode = 200, $data = null): JsonResponse
    {
        // Handle paginated responses
        if ($data instanceof AbstractPaginator) {
            // Check if it's cursor pagination
            if (method_exists($data, 'nextCursor')) {
                return response()->json([
                    "status_code" => $statusCode,
                    "message" => $message,
                    "data" => $data->items(),
                    "meta" => [
                        "per_page" => $data->perPage(),
                        "next_cursor" => $data->nextCursor()?->encode(),
                        "prev_cursor" => $data->previousCursor()?->encode(),
                    ],
                    "links" => [
                        "next" => $data->nextPageUrl(),
                        "prev" => $data->previousPageUrl(),
                    ]
                ], $statusCode);
            }

            // Standard offset-based pagination
            return response()->json([
                "status_code" => $statusCode,
                "message" => $message,
                "data" => $data->items(),
                "meta" => [
                    "current_page" => $data->currentPage(),
                    "per_page" => $data->perPage(),
                    "total" => $data->total(),
                    "last_page" => $data->lastPage(),
                ],
                "links" => [
                    "first" => $data->url(1),
                    "last" => $data->url($data->lastPage()),
                    "next" => $data->nextPageUrl(),
                    "prev" => $data->previousPageUrl(),
                ]
            ], $statusCode);
        }

        return response()->json([
            "status_code" => $statusCode,
            "message" => $message,
            "data" => $data
        ], $statusCode);
    }

    public function handleException(Exception $exception): JsonResponse
    {
        $status = (int) ($exception->getCode() ?: 500);
        if (!in_array($status, [200, 201, 400, 401, 403, 404, 409, 422, 429, 500])) {
            $status = 500;
        }
        return response()->json([
            "status_code" => $status,
            "message" => $exception->getMessage() ?? "",
            "data" => null
        ], $status);
    }
}
