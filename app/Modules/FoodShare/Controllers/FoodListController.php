<?php

namespace App\Modules\FoodShare\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FoodShare\Services\FoodListService;
use App\Modules\FoodShare\Data\FoodListData;
use App\Modules\FoodShare\Requests\FoodListRequest;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\JsonResponse;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\Validator;

class FoodListController extends Controller
{
    public function __construct(protected FoodListService $foodlistService) {}

    public function index(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $params = $request->query();
            $response = $this->foodlistService->index($params);

            DB::commit();
            return $this->success('Food Lists fetched Successfully', Response::HTTP_OK, $response);
        } catch (Exception $exception) {
            DB::rollBack();
            return $this->handleException($exception);
        }
    }

    public function show(string $id): JsonResponse
    {
        DB::beginTransaction();
        try {
            $response = $this->foodlistService->show($id);

            DB::commit();
            return $this->success('Food List fetched Successfully', Response::HTTP_OK, $response);
        } catch (Exception $exception) {
            DB::rollBack();
            return $this->handleException($exception);
        }
    }

    public function storeDonate(FoodListRequest $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();
            $this->foodlistService->store($data);

            DB::commit();
            return $this->success('Food Donate List Initiated Successfully', Response::HTTP_CREATED);
        } catch (Exception $exception) {
            DB::rollBack();
            return $this->handleException($exception);
        }
    }

    public function storeRequest(FoodListRequest $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();
            $this->foodlistService->store($data);

            DB::commit();
            return $this->success('Food Request Initiated Successfully', Response::HTTP_CREATED);
        } catch (Exception $exception) {
            DB::rollBack();
            return $this->handleException($exception);
        }
    }
}
