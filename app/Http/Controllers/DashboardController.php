<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Quotation;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $quotationQuery = Quotation::query();
        if (! $user->isSuperAdmin()) {
            $quotationQuery->where('user_id', $user->id);
        }

        $stats = [
            'total_customers' => Customer::count(),
            'total_products' => Product::count(),
            'draft_quotations' => (clone $quotationQuery)->where('status', 'draft')->where('document_status', '!=', 'quotation_sent')->count(),
            'sent_quotations' => (clone $quotationQuery)->where('status', 'draft')->where('document_status', 'quotation_sent')->count(),
            'approved_quotations' => (clone $quotationQuery)->where('status', 'approved')->count(),
            'total_invoiced' => Invoice::sum('total_amount'),
        ];

        $recentQuotations = (clone $quotationQuery)
            ->with(['customer', 'user'])
            ->latest()
            ->take(10)
            ->get();

        $outstandingCustomers = Customer::withSum('ledgers', 'amount')
            ->get()
            ->map(function ($customer) {
                $customer->balance = (float) $customer->opening_balance + (float) ($customer->ledgers_sum_amount ?? 0);
                return $customer;
            })
            ->filter(fn ($c) => $c->balance > 0)
            ->sortByDesc('balance')
            ->take(5);

        return view('dashboard', compact('stats', 'recentQuotations', 'outstandingCustomers'));
    }
}
