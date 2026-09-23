<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quotation;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $query = Quotation::where('user_id', $request->user()->id)->where('is_temporary', false);

        return response()->json([
            'data' => [
                'quotation_counts' => [
                    'all' => (clone $query)->count(),
                    'created' => (clone $query)->where('status', 'draft')->where('document_status', '!=', 'quotation_sent')->count(),
                    'sent' => (clone $query)->where('status', 'draft')->where('document_status', 'quotation_sent')->count(),
                    'approved' => (clone $query)->where('status', 'approved')->count(),
                    'rejected' => (clone $query)->where('status', 'rejected')->count(),
                ],
                'recent_quotations' => (clone $query)->with(['customer', 'invoice'])->latest()->limit(10)->get()
                    ->map(fn (Quotation $quotation) => QuotationController::summary($quotation)),
            ],
        ]);
    }
}
