<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Request\LoginRequest;
use App\Modules\User\Request\SignupRequest;
use App\Modules\User\Services\UserService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Exception;

class UserController extends Controller
{
    public function __construct(protected UserService $userService) {}

    public function register(SignupRequest $request): JsonResponse
    {
        try {
            $details = $request->validated();

            $this->userService->store($details);

            return $this->success("Registered Successfully", Response::HTTP_CREATED);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode());
        }
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $details = $request->validated();

            $response = $this->userService->login($details);

            return $this->success("Logged In Successfully", Response::HTTP_ACCEPTED, $response);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode());
        }
    }
}
