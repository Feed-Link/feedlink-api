<?php

use App\Modules\FoodShare\Controllers\FoodListController;
use App\Modules\FoodShare\Controllers\FoodRequestController;
use App\Modules\User\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()
        ->json(['message' => 'Application is running'], 200);
});

/**
 * ====================================
 *        Authentication Routes
 * ====================================
 */
Route::prefix('auth')
    ->group(function () {
        Route::post('register', [UserController::class, 'register']);
        Route::post('login', [UserController::class, 'login']);
        Route::get('logout', [UserController::class, 'logout'])->middleware('auth:api');
        Route::post('verify-otp', [UserController::class, 'verifyOTP']);
        Route::post('resend-otp', [UserController::class, 'resendOTP']);
        Route::post('refresh-token', [UserController::class, 'refreshToken']);
        Route::post('forgot-password', [UserController::class, 'forgotPassword']);
        Route::post('reset-password', [UserController::class, 'resetPassword']);
    });

/**
 * ====================================
 *        Food Listings Routes
 * ====================================
 */
Route::prefix('foodlist')
    ->middleware('auth:api')
    ->group(function () {
        /**
         * ====================================
         *        Food Request Routes
         * ====================================
         */
        Route::post('{id}/request', [FoodRequestController::class, 'requestFood']);
        Route::get('/', [FoodListController::class, 'index']);
        Route::get('{id}', [FoodListController::class, 'show']);
        Route::delete('{id}', [FoodListController::class, 'destroy']);
        Route::post('donate', [FoodListController::class, 'storeDonate'])
            ->middleware('permission:foodlist.create.donate')
            ->name('donate');
        Route::post('request', [FoodListController::class, 'storeRequest'])
            ->middleware('permission:foodlist.create.request')
            ->name('request');
    });
