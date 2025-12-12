<?php

use App\Modules\User\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()
        ->json(['message' => 'Application is running'], 200);
});

Route::prefix('auth')
    ->group(function () {
        Route::post('register', [UserController::class, 'register']);
        Route::post('login', [UserController::class, 'login']);
        Route::get('logout', [UserController::class, 'logout'])->middleware('auth:api');
        Route::post('verify-otp', [UserController::class, 'verifyOTP']);
        Route::post('resend-otp', [UserController::class, 'resendOTP']);
    });
