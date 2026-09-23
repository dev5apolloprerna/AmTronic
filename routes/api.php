<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\QuotationController;
use Illuminate\Support\Facades\Route;

    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('api.token')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
    Route::post('forgot-password/send-otp', [AuthController::class, 'sendPasswordResetOtp'])->middleware('throttle:3,1');
    Route::post('forgot-password/reset', [AuthController::class, 'resetPassword']);



    Route::post('change-password', [AuthController::class, 'changePassword']);
    Route::post('profile', [AuthController::class, 'profile']);
    Route::post('profile/update', [AuthController::class, 'updateProfile']);

    Route::post('dashboard', DashboardController::class);

    Route::post('quotations/list', [QuotationController::class, 'index']);
    Route::post('quotations/pending', [QuotationController::class, 'pending']);
    Route::post('quotations/approved', [QuotationController::class, 'approved']);
    Route::post('quotations/rejected', [QuotationController::class, 'rejected']);

    Route::post('quotations/create', [QuotationController::class, 'store']);
    Route::post('quotations/{quotation}/show', [QuotationController::class, 'show']);
    Route::post('quotations/{quotation}/update', [QuotationController::class, 'update']);
    Route::post('quotations/{quotation}/delete', [QuotationController::class, 'destroy']);


    Route::post('quotations/items', [QuotationController::class, 'storeItem']);
    Route::post('quotations/{quotation}/items', [QuotationController::class, 'storeItem']);
    Route::post('quotations/{quotation}/items/{item}/update', [QuotationController::class, 'updateItem']);
    Route::post('quotations/{quotation}/items/{item}/delete', [QuotationController::class, 'destroyItem']);


    Route::post('quotations/{quotation}/mark-sent', [QuotationController::class, 'markSent']);
    Route::post('quotations/{quotation}/approve', [QuotationController::class, 'approve']);
    Route::post('quotations/{quotation}/reject', [QuotationController::class, 'reject']);
});
