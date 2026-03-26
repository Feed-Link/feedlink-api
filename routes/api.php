<?php

use App\Modules\Admin\Controllers\AdminUserController;
use App\Modules\Admin\Controllers\AdminFoodListController;
use App\Modules\Admin\Controllers\AdminFoodRequestController;
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

/**
 * ====================================
 *        Admin Routes
 * ====================================
 */
Route::prefix('admin')
    ->middleware(['auth:api', 'is_admin_user'])
    ->group(function () {
        /**
         * Users Management
         */
        Route::prefix('users')
            ->group(function () {
                Route::get('/', [AdminUserController::class, 'index']);
                Route::post('/', [AdminUserController::class, 'store']);
                Route::get('/stats', [AdminUserController::class, 'getStats']);
                Route::get('{id}', [AdminUserController::class, 'show']);
                Route::put('{id}', [AdminUserController::class, 'update']);
                Route::delete('{id}', [AdminUserController::class, 'destroy']);
            });

        /**
         * Food Listings Management
         */
        Route::prefix('foodlists')
            ->group(function () {
                Route::get('/', [AdminFoodListController::class, 'index']);
                Route::post('/', [AdminFoodListController::class, 'store']);
                Route::get('/stats', [AdminFoodListController::class, 'getStats']);
                Route::get('{id}', [AdminFoodListController::class, 'show']);
                Route::put('{id}', [AdminFoodListController::class, 'update']);
                Route::delete('{id}', [AdminFoodListController::class, 'destroy']);
            });

        /**
         * Food Requests Management
         */
        Route::prefix('food-requests')
            ->group(function () {
                Route::get('/', [AdminFoodRequestController::class, 'index']);
                Route::post('/', [AdminFoodRequestController::class, 'store']);
                Route::get('/stats', [AdminFoodRequestController::class, 'getStats']);
                Route::get('{id}', [AdminFoodRequestController::class, 'show']);
                Route::put('{id}', [AdminFoodRequestController::class, 'update']);
                Route::delete('{id}', [AdminFoodRequestController::class, 'destroy']);
            });
    });
