<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;

class InvoiceController extends Controller
{
    public function show(Invoice $invoice)
    {
        $this->authorizeAccess($invoice);
        $invoice->load(['customer', 'quotation.items.product', 'quotation.user', 'payments.enteredBy', 'deliveryChallan']);

        $totalPaid = $invoice->totalPaid();
        $balanceDue = $invoice->balanceDue();

        return view('invoices.show', compact('invoice', 'totalPaid', 'balanceDue'));
    }

    public function download(Invoice $invoice)
    {
        $this->authorizeAccess($invoice);
        $invoice->load(['customer', 'quotation.items.product', 'quotation.user']);

        $pdf = Pdf::loadView('invoices.pdf', compact('invoice'))->setPaper('a4');

        return $pdf->stream($invoice->invoice_number . '.pdf');
    }
        public function markSent(Invoice $invoice)
    {
        $this->authorizeAccess($invoice);
        $invoice->update(['document_status' => 'invoice_approved']);

        return back()->with('success', 'Invoice sent and approved.');
    }


    private function authorizeAccess(Invoice $invoice): void
    {
        $user = Auth::user();
        if (! $user->isSuperAdmin() && $invoice->quotation->user_id !== $user->id) {
            abort(403, 'You do not have permission to access this invoice.');
        }
    }
}
