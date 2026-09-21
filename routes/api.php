<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\QuotationController;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login']);

Route::middleware('api.token')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::put('change-password', [AuthController::class, 'changePassword']);
    Route::get('dashboard', DashboardController::class);
    Route::get('quotation-lookups', [QuotationController::class, 'lookups']);
    Route::apiResource('quotations', QuotationController::class);
    Route::post('quotations/{quotation}/mark-sent', [QuotationController::class, 'markSent']);
    Route::post('quotations/{quotation}/approve', [QuotationController::class, 'approve']);
    Route::post('quotations/{quotation}/reject', [QuotationController::class, 'reject']);
});
