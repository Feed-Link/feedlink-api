<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Request\LoginRequest;
use App\Modules\User\Request\SignupRequest;
use App\Modules\User\Services\UserService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Request;
use Exception;

class UserController extends Controller
{
    public function __construct(protected UserService $userService) {}

    public function register(SignupRequest $request): JsonResponse
    {
        try {
            $details = $request->validated();

            $user = $this->userService->store($details);

            return $this->success("Registered Successfully", Response::HTTP_CREATED, $user);
        } catch (Exception $exception) {
            return $this->handleException($exception);
        }
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $details = $request->validated();

            $response = $this->userService->login($details);

            return $this->success("Logged In Successfully", Response::HTTP_ACCEPTED, $response);
        } catch (Exception $exception) {
            return $this->handleException($exception);
        }
    }

    public function verifyOTP(Request $request): JsonResponse
    {
        try {
            $otp = $request->validate([
                'otp' => 'required|digits:6',
                'email' => 'required|email|exists:users,email'
            ]);

            $response = $this->userService->verifyOTP($otp);

            return $this->success("OTP Verified Successfully", Response::HTTP_OK, $response);
        } catch (Exception $exception) {
            return $this->handleException($exception);
        }
    }

    public function resendOTP(Request $request): JsonResponse
    {
        try {
            $email = $request->validate([
                'email' => 'required|email|exists:users,email'
            ]);

            $this->userService->resendOTP($email);

            return $this->success("OTP Resend Successfully", Response::HTTP_OK);
        } catch (Exception $exception) {
            return $this->handleException($exception);
        }
    }
}
