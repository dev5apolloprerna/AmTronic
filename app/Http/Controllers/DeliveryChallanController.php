<?php
namespace App\Http\Controllers;

use App\Models\DeliveryChallan;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;

class DeliveryChallanController extends Controller
{
    public function store(Invoice $invoice)
    {
        $this->authorizeInvoice($invoice);
        $challan = $invoice->deliveryChallan()->firstOrCreate([], [
            'challan_number' => 'DC-' . now()->format('Ymd') . '-' . str_pad((string) $invoice->id, 5, '0', STR_PAD_LEFT),
            'challan_date' => now()->toDateString(),
        ]);
        return redirect()->route('delivery-challans.show', $challan)->with('success', 'Delivery challan created.');
    }
    public function show(DeliveryChallan $deliveryChallan)
    {
        $this->authorizeInvoice($deliveryChallan->invoice);
        $deliveryChallan->load('invoice.customer', 'invoice.quotation.items.product');
        return view('delivery-challans.show', compact('deliveryChallan'));
    }
    public function download(DeliveryChallan $deliveryChallan)
    {
        $this->authorizeInvoice($deliveryChallan->invoice);
        $deliveryChallan->load('invoice.customer', 'invoice.quotation.items.product');
        return Pdf::loadView('delivery-challans.pdf', compact('deliveryChallan'))->setPaper('a4')->stream($deliveryChallan->challan_number . '.pdf');
    }
    private function authorizeInvoice(Invoice $invoice): void
    {
        if (! Auth::user()->isSuperAdmin() && $invoice->quotation->user_id !== Auth::id()) abort(403);
    }
}
