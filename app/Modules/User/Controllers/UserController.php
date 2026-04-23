<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Requests\DeviceTokenRequest;
use App\Modules\User\Request\ForgotPasswordRequest;
use App\Modules\User\Request\LoginRequest;
use App\Modules\User\Request\ResetPasswordRequest;
use App\Modules\User\Request\SignupRequest;
use App\Modules\User\Services\UserService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    public function __construct(protected UserService $userService) {}

    public function register(SignupRequest $request): JsonResponse
    {
        try {
            $details = $request->validated();

            $user = $this->userService->store($details);

            return $this->success('Registered Successfully', Response::HTTP_CREATED, $user);
        } catch (Exception $exception) {
            return $this->handleException($exception);
        }
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $details = $request->validated();

            $response = $this->userService->login($details);

            return $this->success('Logged In Successfully', Response::HTTP_ACCEPTED, $response);
        } catch (Exception $exception) {
            return $this->handleException($exception);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            $this->userService->logout($request, $request->input('refresh_token'));

            return $this->success('Logged Out Successfully', Response::HTTP_OK);
        } catch (Exception $exception) {
            return $this->handleException($exception);
        }
    }

    public function verifyOTP(Request $request): JsonResponse
    {
        try {
            $otp = $request->validate([
                'otp' => 'required|digits:6',
                'email' => 'required|email|exists:users,email',
            ]);

            $response = $this->userService->verifyOTP($otp);

            return $this->success('OTP Verified Successfully', Response::HTTP_OK, $response);
        } catch (Exception $exception) {
            return $this->handleException($exception);
        }
    }

    public function resendOTP(Request $request): JsonResponse
    {
        try {
            $email = $request->validate([
                'email' => 'required|email|exists:users,email',
            ]);

            $this->userService->resendOTP($email);

            return $this->success('OTP Resend Successfully', Response::HTTP_OK);
        } catch (Exception $exception) {
            return $this->handleException($exception);
        }
    }

    public function refreshToken(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'refresh_token' => 'required|string',
            ]);

            $response = $this->userService->refreshToken($validated['refresh_token']);

            return $this->success('Token Refreshed Successfully', Response::HTTP_OK, $response);
        } catch (Exception $exception) {
            return $this->handleException($exception);
        }
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        try {
            $details = $request->validated();
            $this->userService->forgotPassword($details);

            return $this->success('Password reset OTP sent successfully', Response::HTTP_OK);
        } catch (Exception $exception) {
            return $this->handleException($exception);
        }
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        try {
            $details = $request->validated();
            $response = $this->userService->resetPassword($details);

            return $this->success('Password reset successfully', Response::HTTP_OK, $response);
        } catch (Exception $exception) {
            return $this->handleException($exception);
        }
    }

    /**
     * GET /api/user/profile
     */
    public function profile(): JsonResponse
    {
        try {
            $user = Auth::user();

            if (! $user) {
                throw new Exception('User not found', Response::HTTP_NOT_FOUND);
            }

            return $this->success(
                'Profile retrieved successfully',
                Response::HTTP_OK,
                [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'contact' => $user->contact,
                    'is_verified' => (bool) ($user->is_verified ?? false),
                    'profile_photo' => $user->profile_photo,
                    'latitude' => $user->latitude,
                    'longitude' => $user->longitude,
                    'location' => $user->latitude && $user->longitude ? [
                        'lat' => (float) $user->latitude,
                        'lng' => (float) $user->longitude,
                    ] : null,
                    'roles' => $user->roles->pluck('name'),
                ]
            );
        } catch (Exception $exception) {
            return $this->handleException($exception);
        }
    }

    /**
     * PUT /api/user/profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (! $user) {
                throw new Exception('User not found', Response::HTTP_NOT_FOUND);
            }

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'contact' => 'sometimes|string|max:20',
                'profile_photo' => 'sometimes|string',
            ]);

            $user->update($validated);
            $user->refresh();

            return $this->success(
                'Profile updated successfully',
                Response::HTTP_OK,
                [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'contact' => $user->contact,
                    'is_verified' => (bool) ($user->is_verified ?? false),
                    'profile_photo' => $user->profile_photo,
                    'latitude' => $user->latitude,
                    'longitude' => $user->longitude,
                    'location' => $user->latitude && $user->longitude ? [
                        'lat' => (float) $user->latitude,
                        'lng' => (float) $user->longitude,
                    ] : null,
                    'roles' => $user->roles->pluck('name'),
                ]
            );
        } catch (Exception $exception) {
            return $this->handleException($exception);
        }
    }

    public function registerDeviceToken(DeviceTokenRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $user->update(['fcm_token' => $request->validated()['fcm_token']]);

            return $this->success('Device token registered', Response::HTTP_OK);
        } catch (Exception $exception) {
            return $this->handleException($exception);
        }
    }
}
