<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryChallan;
use App\Models\Invoice;
use App\Models\Quotation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function quotation(Request $request, Quotation $quotation)
    {
        $this->authorizeQuotation($request, $quotation);
        abort_if($quotation->is_temporary, 404);

        $quotation->loadMissing(['items.product', 'items.material', 'customer', 'user']);

        return Pdf::loadView('quotations.pdf', compact('quotation'))
            ->setPaper('a4', 'portrait')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isFontSubsettingEnabled', true)
            ->setOption('dpi', 96)
            ->setWarnings(false)
            ->stream($quotation->quotation_number.'.pdf');
    }

    public function invoice(Request $request, Invoice $invoice)
    {
        $invoice->loadMissing('quotation');
        $this->authorizeQuotation($request, $invoice->quotation);
        $invoice->loadMissing(['customer', 'quotation.items.product', 'quotation.items.material', 'quotation.user']);

        return Pdf::loadView('invoices.pdf', compact('invoice'))
            ->setPaper('a4')
            ->stream($invoice->invoice_number.'.pdf');
    }

    public function deliveryChallan(Request $request, DeliveryChallan $deliveryChallan)
    {
        $deliveryChallan->loadMissing('invoice.quotation');
        $this->authorizeQuotation($request, $deliveryChallan->invoice->quotation);
        $deliveryChallan->loadMissing([
            'invoice.customer',
            'invoice.quotation.items.product',
            'invoice.quotation.items.material',
        ]);

        return Pdf::loadView('delivery-challans.pdf', compact('deliveryChallan'))
            ->setPaper('a4')
            ->stream($deliveryChallan->challan_number.'.pdf');
    }

    private function authorizeQuotation(Request $request, Quotation $quotation): void
    {
        abort_unless(
            $quotation->user_id === $request->user()->id,
            403,
            'You do not have permission to access this document.',
        );
    }
}
