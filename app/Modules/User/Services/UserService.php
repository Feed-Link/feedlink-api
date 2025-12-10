<?php

namespace App\Modules\User\Services;

use App\Modules\User\Repositories\UserRepository;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Exception;

class UserService
{
    public function __construct(protected UserRepository $userRepository) {}

    public function store(array $details): mixed
    {
        try {
            return $this->userRepository->store($details);
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function login(array $details): mixed
    {
        try {
            $user = $this->userRepository->fetchBy('email', $details['email']);

            if (is_null($user)) {
                throw new Exception('User not found', Response::HTTP_NOT_FOUND);
            }

            if (!Auth::attempt($details)) {
                throw new Exception('Incorrect password', Response::HTTP_UNAUTHORIZED);
            }

            $token = $user->createToken('feedlink-app')->accessToken;

            return $token;
        } catch (Exception $e) {
            throw $e;
        }
    }
}
