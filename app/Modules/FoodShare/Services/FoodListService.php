<?php

namespace App\Modules\FoodShare\Services;

use App\Modules\FoodShare\Repositories\FoodListRepository;
use Exception;

class FoodListService
{
    public function __construct(protected FoodListRepository $foodlistRepository) {}

    public function store(array $foodList): object
    {
        try {
            $userID = auth()->user()->id;

            $foodList['user_id'] = $userID;

            $created = $this->foodlistRepository->store($foodList);
            return $created;
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function index(array $params): object
    {
        try {
            $foodlists = $this->foodlistRepository->fetchAll($params, ['user']);

            return $foodlists;
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function show(string $id): object
    {
        try {
            $foodlist = $this->foodlistRepository->fetchBy('id', $id, ['user']);

            return $foodlist;
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function destroy(string $id): void
    {
        try {
            $this->foodlistRepository->delete($id);
        } catch (Exception $e) {
            throw $e;
        }
    }
}
