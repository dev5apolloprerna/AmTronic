<?php
    use App\Http\Controllers\Api\AuthController;
    use App\Http\Controllers\Api\DashboardController;
    use App\Http\Controllers\Api\DocumentController;
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
 
    // Keep these literal paths before the {quotation} routes. Otherwise Laravel
    // treats "items" as a quotation ID and returns a model-binding 404.
     Route::post('quotations/items', [QuotationController::class, 'storeItem']);
     Route::post('quotations/items/update', [QuotationController::class, 'updateItem']);
     Route::post('quotations/items/delete', [QuotationController::class, 'destroyItem']);
 
     Route::post('quotations/create', [QuotationController::class, 'store']);
     Route::post('quotations/{quotation}/show', [QuotationController::class, 'show'])->whereNumber('quotation');
     Route::post('quotations/{quotation}/update', [QuotationController::class, 'update'])->whereNumber('quotation');
     Route::post('quotations/{quotation}/delete', [QuotationController::class, 'destroy'])->whereNumber('quotation');
 
 
     Route::post('quotations/{quotation}/mark-sent', [QuotationController::class, 'markSent'])->whereNumber('quotation');
    Route::post('quotations/{quotation}/approve', [QuotationController::class, 'approve'])->whereNumber('quotation');
   Route::post('quotations/{quotation}/reject', [QuotationController::class, 'reject'])->whereNumber('quotation');

    // These PDFs use the same Blade templates as the web application. API
    // clients must include their bearer token when following the returned URL.
    Route::get('quotations/{quotation}/pdf', [DocumentController::class, 'quotation'])
        ->whereNumber('quotation')->name('api.quotations.pdf');
    Route::get('invoices/{invoice}/pdf', [DocumentController::class, 'invoice'])
        ->whereNumber('invoice')->name('api.invoices.pdf');
    Route::get('delivery-challans/{deliveryChallan}/pdf', [DocumentController::class, 'deliveryChallan'])
        ->whereNumber('deliveryChallan')->name('api.delivery-challans.pdf');
 });
