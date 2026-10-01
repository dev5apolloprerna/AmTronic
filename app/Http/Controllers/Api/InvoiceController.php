<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerLedger;
use App\Models\Invoice;
use App\Models\Quotation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function store(Request $request, Quotation $quotation)
    {
        $this->owned($request, $quotation);

        if (! $quotation->isSent() && $quotation->status !== 'approved') {
            return response()->json(['message' => 'Send the quotation before generating an invoice.'], 409);
        }

        if ($quotation->invoice()->exists()) {
            return response()->json(['message' => 'Invoice has already been generated.'], 409);
        }

        $request->merge([
            'invoice_number' => trim((string) $request->input('invoice_number')),
            'other_reference' => trim((string) $request->input('other_reference')),
        ]);

        $data = $request->validate([
            'invoice_number' => ['required', 'string', 'max:255', 'unique:invoices,invoice_number'],
            'other_reference' => ['nullable', 'string', 'max:255'],
            'invoice_date' => ['required', 'date'],
        ]);

        DB::transaction(function () use ($request, $quotation, $data) {
            $invoice = Invoice::create([
                'invoice_number' => $data['invoice_number'],
                'other_reference' => $data['other_reference'] ?: null,
                'quotation_id' => $quotation->id,
                'customer_id' => $quotation->customer_id,
                'invoice_date' => $data['invoice_date'],
                'sub_total' => $quotation->sub_total,
                'gst_amount' => $quotation->gst_amount,
                'discount_amount' => $quotation->discount_amount,
                'admin_charges' => $quotation->admin_charges,
                'material_handling_charges' => $quotation->material_handling_charges,
                'round_off' => $quotation->round_off,
                'total_amount' => $quotation->total_amount,
                'shipping_address' => $quotation->shipping_address,
                'shipping_address_line_2' => $quotation->shipping_address_line_2,
                'shipping_state' => $quotation->shipping_state,
                'shipping_city' => $quotation->shipping_city,
                'shipping_pincode' => $quotation->shipping_pincode,
                'cgst_amount' => $quotation->cgst_amount,
                'sgst_amount' => $quotation->sgst_amount,
                'igst_amount' => $quotation->igst_amount,
                'document_status' => 'invoice_ready',
            ]);

            CustomerLedger::create([
                'customer_id' => $quotation->customer_id,
                'transaction_date' => $invoice->invoice_date,
                'amount' => $quotation->total_amount,
                'description' => 'Invoice '.$invoice->invoice_number,
                'reference_type' => 'invoice',
                'reference_id' => $invoice->id,
                'entered_by' => $request->user()->id,
                'balance_after' => $quotation->customer->currentBalance() + (float) $quotation->total_amount,
            ]);
        });

        $quotation->refresh();

        return response()->json([
            'message' => 'Invoice generated successfully.',
            'data' => QuotationController::summary($quotation),
        ], 201);
    }

    public function storeDeliveryChallan(Request $request, Invoice $invoice)
    {
        $invoice->loadMissing('quotation');
        $this->owned($request, $invoice->quotation);

        if ($invoice->deliveryChallan()->exists()) {
            return response()->json(['message' => 'Delivery challan has already been generated.'], 409);
        }

        $invoice->deliveryChallan()->create([
            'challan_number' => 'DC-'.now()->format('Ymd').'-'.str_pad((string) $invoice->id, 5, '0', STR_PAD_LEFT),
            'challan_date' => now()->toDateString(),
        ]);

        $invoice->quotation->refresh();

        return response()->json([
            'message' => 'Delivery challan generated successfully.',
            'data' => QuotationController::summary($invoice->quotation),
        ], 201);
    }

    private function owned(Request $request, Quotation $quotation): void
    {
        abort_unless(
            $quotation->user_id === $request->user()->id,
            403,
            'You do not have permission to access this quotation.',
        );
    }
}
