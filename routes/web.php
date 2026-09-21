<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\DeliveryChallanController;
use App\Http\Controllers\NumberSettingController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Guest routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
});

// Authenticated routes
  Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Quotations - accessible to both roles (user creates/manages own, super_admin sees all)
    Route::resource('quotations', QuotationController::class);
    Route::post('quotations/{quotation}/approve', [QuotationController::class, 'approve'])->name('quotations.approve');
        Route::post('quotations/{quotation}/generate-invoice', [QuotationController::class, 'generateInvoice'])->name('quotations.generate-invoice');
    Route::post('quotations/{quotation}/reject', [QuotationController::class, 'reject'])->name('quotations.reject');
    Route::post('quotations/{quotation}/duplicate', [QuotationController::class, 'duplicate'])->name('quotations.duplicate');
    Route::get('quotations/{quotation}/download', [QuotationController::class, 'download'])->name('quotations.download');
    Route::post('quotations/{quotation}/mark-sent', [QuotationController::class, 'markSent'])->name('quotations.mark-sent');


    Route::get('ajax/last-price', [QuotationController::class, 'lastPrice'])->name('quotations.last-price');

    // Invoices
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('invoices/{invoice}/download', [InvoiceController::class, 'download'])->name('invoices.download');
    Route::post('invoices/{invoice}/mark-sent', [InvoiceController::class, 'markSent'])->name('invoices.mark-sent');
    Route::post('invoices/{invoice}/delivery-challan', [DeliveryChallanController::class, 'store'])->name('delivery-challans.store');
    Route::get('delivery-challans/{deliveryChallan}', [DeliveryChallanController::class, 'show'])->name('delivery-challans.show');
    Route::get('delivery-challans/{deliveryChallan}/download', [DeliveryChallanController::class, 'download'])->name('delivery-challans.download');
    
    // Payment collection against a specific invoice
    Route::get('payment-collections', [PaymentController::class, 'index'])->name('payment-collections.index');
    Route::get('pending-amounts', [PaymentController::class, 'pendingAmounts'])->name('pending-amounts.index');
    Route::get('pending-amounts/export/excel', [PaymentController::class, 'pendingAmountsExcel'])->name('pending-amounts.excel');
    Route::post('payment-collections', [PaymentController::class, 'storeCustomerPayment'])->name('payment-collections.store');
    Route::get('payment-collections/customers/{customer}/history', [PaymentController::class, 'history'])->name('payment-collections.history');
    Route::get('payment-collections/{payment}/receipt', [PaymentController::class, 'receipt'])->name('payment-collections.receipt');
    Route::post('invoices/{invoice}/payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::delete('payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');


    // Customer ledger entries - both roles may enter payments
    Route::post('customers/{customer}/ledger', [CustomerController::class, 'storeLedgerEntry'])->name('customers.ledger.store');
   // Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');

    // Super Admin only
    Route::middleware('role:super_admin')->group(function () {
        Route::resource('customers', CustomerController::class)->except(['show']);
        Route::resource('products', ProductController::class);
        Route::resource('users', UserController::class)->except(['show']);

        Route::get('number-settings', [NumberSettingController::class, 'index'])->name('number-settings.index');
        Route::put('number-settings/{numberSetting}', [NumberSettingController::class, 'update'])->name('number-settings.update');

        Route::get('reports/customer-ledger', [ReportController::class, 'customerLedger'])->name('reports.customer-ledger');
        Route::get('reports/sales', [ReportController::class, 'sales'])->name('reports.sales');
        Route::get('reports/customer-ledger/export/excel', [ReportController::class, 'customerLedgerExcel'])->name('reports.customer-ledger.excel');
        Route::get('reports/customer-ledger/export/pdf', [ReportController::class, 'customerLedgerPdf'])->name('reports.customer-ledger.pdf');
        Route::get('reports/sales/export/excel', [ReportController::class, 'salesExcel'])->name('reports.sales.excel');
        Route::get('reports/sales/export/pdf', [ReportController::class, 'salesPdf'])->name('reports.sales.pdf');
    });
        Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
});


Route::get('/check-logo-path', function () {
    return [
        'public_path' => public_path(),
        'logo_path' => public_path('images/glass-grip-logo.png'),
        'exists' => file_exists(public_path('images/glass-grip-logo.png')),
    ];
});
