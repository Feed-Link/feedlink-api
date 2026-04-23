<?php

use App\Modules\FoodListings\Controllers\DonorFoodListingController;
use App\Modules\FoodListings\Controllers\RecipientFoodListingController;
use App\Modules\FoodListings\Controllers\NearbyListingController;
use App\Modules\FoodListings\Controllers\NearbyRequestController;
use App\Modules\FoodListings\Controllers\UserLocationController;
use App\Modules\Upload\Controllers\UploadController;
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
 *        Donor Food Listing Routes
 * ====================================
 */
Route::prefix('donor')
    ->middleware(['auth:api', 'role:donor'])
    ->group(function () {
        Route::get('listings', [DonorFoodListingController::class, 'index']);
        Route::post('listings', [DonorFoodListingController::class, 'store']);
        Route::get('listings/{id}', [DonorFoodListingController::class, 'show']);
        Route::put('listings/{id}', [DonorFoodListingController::class, 'update']);
        Route::delete('listings/{id}', [DonorFoodListingController::class, 'destroy']);
        Route::get('listings/{listingId}/claims', [DonorFoodListingController::class, 'claims']);
        Route::post('listings/{listingId}/claims/{claimId}/confirm', [DonorFoodListingController::class, 'confirmClaim']);
        Route::post('listings/{listingId}/claims/{claimId}/reject', [DonorFoodListingController::class, 'rejectClaim']);
    });

/**
 * ====================================
 *        Recipient Food Listing Routes
 * ====================================
 */
Route::prefix('recipient')
    ->middleware(['auth:api', 'role:recipient'])
    ->group(function () {
        Route::get('listings', [RecipientFoodListingController::class, 'index']);
        Route::get('listings/{id}', [RecipientFoodListingController::class, 'show']);
        Route::post('listings/{listingId}/claim', [RecipientFoodListingController::class, 'claim']);
        Route::delete('listings/{listingId}/claim', [RecipientFoodListingController::class, 'cancelClaim']);
        Route::get('claims', [RecipientFoodListingController::class, 'myClaims']);
    });

/**
 * ====================================
 *        Nearby / Shared Routes
 * ====================================
 */
Route::middleware(['auth:api'])
    ->group(function () {
        Route::get('listings/nearby', [NearbyListingController::class, 'index']);
        Route::get('requests/nearby', [NearbyRequestController::class, 'index']);
        Route::put('user/location', [UserLocationController::class, 'update']);
        Route::get('user/profile', [UserController::class, 'profile']);
        Route::put('user/profile', [UserController::class, 'updateProfile']);
        Route::post('upload/photo', [UploadController::class, 'photo']);
    });
