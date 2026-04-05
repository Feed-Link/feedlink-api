<?php

namespace App\Modules\FoodListings\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FoodListings\Services\UserLocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Exception;
use Symfony\Component\HttpFoundation\Response;

class UserLocationController extends Controller
{
    public function __construct(
        protected UserLocationService $userLocationService
    ) {
    }

    /**
     * PUT /api/user/location
     */
    public function update(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
            ]);

            $this->userLocationService->updateLocation(Auth::id(), $validated);

            $user = Auth::user();
            $user->refresh();

            return $this->success(
                'Location updated successfully',
                Response::HTTP_OK,
                [
                    'latitude' => (float) $user->latitude,
                    'longitude' => (float) $user->longitude,
                    'location' => [
                        'lat' => (float) $user->latitude,
                        'lng' => (float) $user->longitude,
                    ],
                ]
            );
        } catch (Exception $exception) {
            return $this->handleException($exception);
        }
    }
}
