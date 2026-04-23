<?php

namespace App\Modules\User\Services;

use App\Models\RefreshToken;
use App\Modules\FoodSafety\Entities\UserAcceptance;
use App\Modules\User\Jobs\SendOTPJob;
use App\Modules\User\Repositories\UserRepository;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class UserService
{
    public function __construct(
        protected UserRepository $userRepository,
    ) {}

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
                UserAcceptance::create([
                    'user_id' => $user->id,
                    'terms_version' => UserAcceptance::CURRENT_TERMS_VERSION,
                    'terms_type' => $details['role'],
                    'ip_address' => request()->ip(),
                    'accepted_at' => now(),
                ]);
                SendOTPJob::dispatch($user);
            }

            return $user['email'];
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function login(array $details): array
    {
        try {

            $user = $this->userRepository->fetchBy('email', $details['email']);

            if (
                is_null($user) ||
                ! Auth::guard('web')->attempt($details)
            ) {
                throw new Exception('Invalid credentials', Response::HTTP_NOT_FOUND);
            }

            if (! $user->hasVerifiedEmail()) {
                SendOTPJob::dispatch($user);
                throw new Exception('Email not verified. OTP sent.', Response::HTTP_BAD_REQUEST);
            }

            $token = $user->createToken('feedlink-app')->accessToken;
            $refreshToken = Str::random(64);

            RefreshToken::create([
                'user_id' => $user->id,
                'token' => hash('sha256', $refreshToken),
                'expires_at' => now()->addDays(30),
            ]);

            return [
                'access_token' => $token,
                'refresh_token' => $refreshToken,
                'expires_in' => 1800,
            ];
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function logout(object $request, ?string $refreshTokenString = null): void
    {
        try {
            $user = $request->user();

            if ($user && $user->token()) {
                $user->token()->revoke();
            }

            if ($refreshTokenString) {
                $hashedToken = hash('sha256', $refreshTokenString);
                RefreshToken::where('token', $hashedToken)->update(['revoked' => true]);
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
    public function verifyOTP(array $details): array
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
            $refreshToken = Str::random(64);

            RefreshToken::create([
                'user_id' => $user->id,
                'token' => hash('sha256', $refreshToken),
                'expires_at' => now()->addDays(30),
            ]);

            return [
                'access_token' => $token,
                'refresh_token' => $refreshToken,
                'expires_in' => 1800,
            ];
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function resendOTP(array $details): void
    {
        try {
            $user = $this->userRepository->fetchBy('email', $details['email']);

            if (! is_null($user->email_verified_at)) {
                throw new Exception('User is already verified');
            }

            SendOTPJob::dispatch($user);
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function forgotPassword(array $details): void
    {
        try {
            $user = $this->userRepository->fetchBy('email', $details['email']);

            if (! $user) {
                throw new Exception('User not found', Response::HTTP_NOT_FOUND);
            }

            SendOTPJob::dispatch($user, 'reset');
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function resetPassword(array $details): array
    {
        try {
            $user = $this->userRepository->fetchBy('email', $details['email']);

            if (! $user) {
                throw new Exception('User not found', Response::HTTP_NOT_FOUND);
            }

            $result = $user->consumeOneTimePassword($details['otp']);

            if ($result->value !== 'ok') {
                throw new Exception($result->name, Response::HTTP_BAD_REQUEST);
            }

            $user->password = $details['password'];
            $user->save();

            $token = $user->createToken('feedlink-app')->accessToken;
            $refreshToken = Str::random(64);

            RefreshToken::create([
                'user_id' => $user->id,
                'token' => hash('sha256', $refreshToken),
                'expires_at' => Carbon::now()->addDays(30),
            ]);

            return [
                'access_token' => $token,
                'refresh_token' => $refreshToken,
                'expires_in' => 1800,
            ];
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function refreshToken(string $token): array
    {
        try {
            $hashedToken = hash('sha256', $token);
            $refreshToken = RefreshToken::where('token', $hashedToken)
                ->where('revoked', false)
                ->where('expires_at', '>', Carbon::now())
                ->first();

            if (! $refreshToken || ! $refreshToken->user) {
                throw new Exception('Invalid or expired refresh token', Response::HTTP_UNAUTHORIZED);
            }

            // Revoke the old refresh token
            $refreshToken->update(['revoked' => true]);

            $user = $refreshToken->user;

            $accessToken = $user->createToken('feedlink-app')->accessToken;
            $newRefreshTokenString = Str::random(64);

            RefreshToken::create([
                'user_id' => $user->id,
                'token' => hash('sha256', $newRefreshTokenString),
                'expires_at' => Carbon::now()->addDays(30),
            ]);

            return [
                'access_token' => $accessToken,
                'refresh_token' => $newRefreshTokenString,
                'expires_in' => 1800,
            ];
        } catch (Exception $e) {
            throw $e;
        }
    }
}
