<?php

namespace App\Modules\User\Services;

use App\Modules\User\Jobs\SendOTPJob;
use App\Modules\User\Repositories\UserRepository;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Exception;

use function Symfony\Component\Clock\now;

class UserService
{
    public function __construct(
        protected UserRepository $userRepository,
    ) {
    }

    /**
     * ====================================
     *        Authentication Section
     * ====================================
     */

    public function store(array $details): string
    {
        try {
            $user = $this->userRepository->store($details);

            if (isset($user)) {
                $user->assignRole($details['role']);
                SendOTPJob::dispatch($user);
            }

            return $user['email'];
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function login(array $details): string
    {
        try {

            $user = $this->userRepository->fetchBy('email', $details['email']);

            if (
                is_null($user) ||
                !Auth::guard('web')->attempt($details)
            ) {
                throw new Exception('Invalid credentials', Response::HTTP_NOT_FOUND);
            }

            if (!$user->hasVerifiedEmail()) {
                SendOTPJob::dispatch($user);
                throw new Exception('Email not verified. OTP sent.', Response::HTTP_BAD_REQUEST);
            }

            $token = $user->createToken('feedlink-app')->accessToken;

            return $token;
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function logout(object $request): void
    {
        try {
            $user = $request->user();

            if ($user && $user->token()) {
                $user->token()->revoke();
            }
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * ====================================
     *             OTP Section
     * ====================================
     */

    public function verifyOTP(array $details): string
    {
        try {
            $user = $this->userRepository->fetchBy('email', $details['email']);

            $result = $user->consumeOneTimePassword($details['otp']);

            if ($result->value !== 'ok') {
                throw new Exception($result->name);
            }

            $user->email_verified_at = now();
            $user->save();

            $token = $user->createToken('feedlink-app')->accessToken;

            return $token;
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function resendOTP(array $details): void
    {
        try {
            $user = $this->userRepository->fetchBy('email', $details['email']);

            if (!is_null($user->email_verified_at)) {
                throw new Exception('User is already verified');
            }

            SendOTPJob::dispatch($user);
        } catch (Exception $e) {
            throw $e;
        }
    }
}
