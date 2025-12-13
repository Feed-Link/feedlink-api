<?php

namespace App\Modules\FoodShare\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FoodShare\Services\FoodListService;
use App\Modules\FoodShare\Data\FoodListData;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\JsonResponse;
use Exception;
use Symfony\Component\HttpFoundation\Response;

class FoodRequestController extends Controller
{
    public function __construct(protected FoodListService $foodlistService)
    {
    }

    public function store(FoodListData $data, string $type): JsonResponse
    {
        try {
            DB::beginTransaction();

            $this->foodlistService->store($data, $type);

            return $this->success('Food Listed Successfully', Response::HTTP_CREATED);
        } catch (Exception $exception) {
            DB::rollBack();
            $this->handleException($exception);
        }
    }
}
