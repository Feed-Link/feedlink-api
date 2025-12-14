<?php

namespace App\Modules\FoodShare\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FoodShare\Requests\FoodRequest;
use App\Modules\FoodShare\Services\FoodRequestService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Exception;

class FoodRequestController extends Controller
{
    public function __construct(protected FoodRequestService $foodrequestService) {}

    public function requestFood(FoodRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $data = $request->validated();

            $this->foodrequestService->store($data);

            return $this->success('Food Listed Successfully', Response::HTTP_CREATED);
        } catch (Exception $exception) {
            DB::rollBack();
            return $this->handleException($exception);
        }
    }
}
