<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerLedger;
// use App\Models\DeliveryChallan;
use App\Models\Invoice;
use App\Models\NumberSetting;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class QuotationController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $search = $request->get('search');
        $status = $request->get('status');

        $quotations = Quotation::with(['customer', 'user', 'invoice'])
            ->when(! $user->isSuperAdmin(), fn ($q) => $q->where('user_id', $user->id))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('quotation_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($status === 'sent', fn ($q) => $q->where('status', 'draft')->where('document_status', 'quotation_sent'))
            ->when($status === 'invoice_sent', fn ($q) => $q->whereHas('invoice', fn ($invoice) => $invoice->where('document_status', 'invoice_approved')))
            ->when($status && ! in_array($status, ['sent', 'invoice_sent'], true), function ($q) use ($status) {
                $q->where('status', $status);

                if ($status === 'draft') {
                    $q->where('document_status', '!=', 'quotation_sent');
                }
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('quotations.index', compact('quotations', 'search', 'status'));
    }

    public function create()
    {
        $customers = Customer::orderBy('name')->get();
        $products = Product::where('status', 'active')->orderBy('name')->get();

        return view('quotations.create', compact('customers', 'products'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $subTotal = $this->calculateItemsSubTotal($data['items']);
        if ((float) ($data['discount_amount'] ?? 0) > $subTotal) {
            return back()->withErrors([
                'discount_amount' => 'Discount amount cannot be greater than the Sub Total (₹' . number_format($subTotal, 2) . ').',
            ])->withInput();
        }

        $quotation = DB::transaction(function () use ($data) {
            $quotation = Quotation::create([
                'quotation_number' => NumberSetting::generateNext('quotation'),
                'customer_id' => $data['customer_id'],
                'user_id' => Auth::id(),
                'quotation_date' => $data['quotation_date'],
                'status' => 'draft',
                'gst_applicable' => $data['gst_applicable'] ?? false,
                'discount_amount' => $data['discount_amount'] ?? 0,
                'admin_charges' => $data['admin_charges'] ?? 0,
                'material_handling_charges' => $data['material_handling_charges'] ?? 0,
                ...$this->shippingData($data),
            ]);

            $this->syncItems($quotation, $data['items']);
            $quotation->recalculateTotals();

            return $quotation;
        });

        return redirect()->route('quotations.show', $quotation)->with('success', 'Quotation created successfully.');
    }

    public function show(Quotation $quotation)
    {
        $this->authorizeAccess($quotation);
        $quotation->load(['items.product', 'customer', 'user', 'approvedBy', 'invoice.payments']);

        $totalPaid = $quotation->invoice?->totalPaid();
        $balanceDue = $quotation->invoice?->balanceDue();
        $previousDue = $quotation->customer->currentBalance();
        
        if ($quotation->invoice) {
            $previousDue -= (float) $quotation->invoice->total_amount;
        }

        return view('quotations.show', compact('quotation', 'totalPaid', 'balanceDue', 'previousDue'));
    }
    public function download(Quotation $quotation)
{
    $this->authorizeAccess($quotation);

    $quotation->loadMissing([
        'items.product',
        'customer',
        'user',
    ]);

    $pdf = Pdf::loadView('quotations.pdf', compact('quotation'))
        ->setPaper('a4', 'portrait')
        ->setOption('defaultFont', 'DejaVu Sans')
        ->setOption('isHtml5ParserEnabled', true)
        ->setOption('isFontSubsettingEnabled', true)
        ->setOption('dpi', 96)
        ->setWarnings(false);

    return $pdf->stream(
        $quotation->quotation_number . '.pdf'
    );
}

    public function markSent(Quotation $quotation)
    {
        $this->authorizeAccess($quotation);

        if (! $quotation->isEditable()) {
            return back()->with('error', 'Only newly created quotations can be sent.');
        }

        if ($quotation->items()->count() === 0) {
            return back()->with('error', 'Cannot send a quotation with no items.');
        }

        $quotation->update(['document_status' => 'quotation_sent']);

        return redirect()->route('quotations.show', $quotation)
            ->with('success', 'Quotation marked as sent. You can approve it when the customer accepts it.');
    }

    public function edit(Quotation $quotation)
    {
        $this->authorizeAccess($quotation);

        if (! $quotation->isEditable()) {
            return redirect()->route('quotations.show', $quotation)->with('error', 'Approved quotations cannot be edited.');
        }

        $quotation->load('items.product');
        $customers = Customer::orderBy('name')->get();
        $products = Product::where('status', 'active')->orderBy('name')->get();

        return view('quotations.edit', compact('quotation', 'customers', 'products'));
    }

    public function update(Request $request, Quotation $quotation)
    {
        $this->authorizeAccess($quotation);

        if (! $quotation->isEditable()) {
            return redirect()->route('quotations.show', $quotation)->with('error', 'Approved quotations cannot be edited.');
        }

        $data = $this->validateData($request);

        $subTotal = $this->calculateItemsSubTotal($data['items']);
        if ((float) ($data['discount_amount'] ?? 0) > $subTotal) {
            return back()->withErrors([
                'discount_amount' => 'Discount amount cannot be greater than the Sub Total (₹' . number_format($subTotal, 2) . ').',
            ])->withInput();
        }

        DB::transaction(function () use ($quotation, $data) {
            $quotation->update([
                'customer_id' => $data['customer_id'],
                'quotation_date' => $data['quotation_date'],
                'gst_applicable' => $data['gst_applicable'] ?? false,
                'discount_amount' => $data['discount_amount'] ?? 0,
                'admin_charges' => $data['admin_charges'] ?? 0,
                'material_handling_charges' => $data['material_handling_charges'] ?? 0,
                ...$this->shippingData($data),
            ]);

            $quotation->items()->delete();
            $this->syncItems($quotation, $data['items']);
            $quotation->recalculateTotals();
        });

        return redirect()->route('quotations.show', $quotation)->with('success', 'Quotation updated successfully.');
    }

    public function destroy(Quotation $quotation)
    {
        $this->authorizeAccess($quotation);

        if (! $quotation->isEditable()) {
            return back()->with('error', 'Sent or approved quotations cannot be deleted.');
        }

        $quotation->items()->delete();
        $quotation->delete();

        return redirect()->route('quotations.index')->with('success', 'Quotation deleted successfully.');
    }

    /**
     * Approve the quotation: locks editing and generates an invoice.
     */
    public function approve(Quotation $quotation)
    {
        $this->authorizeAccess($quotation);

        if ($quotation->status === 'approved') {
            return back()->with('error', 'Quotation is already approved.');
        }
         if (! $quotation->isSent()) {
            return back()->with('error', 'Send the quotation before approving it.');
        }

        if ($quotation->items()->count() === 0) {
            return back()->with('error', 'Cannot approve a quotation with no items.');
        }

        $quotation->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'document_status' => 'quotation_sent',
            'approved_at' => now(),
        ]);

        return redirect()->route('quotations.show', $quotation)
            ->with('success', 'Quotation approved successfully. You can now generate the invoice.');
    }

    public function generateInvoice(Request $request, Quotation $quotation)
    {
        $this->authorizeAccess($quotation);

        if (! $quotation->isSent() && $quotation->status !== 'approved') {
            return back()->with('error', 'Send the quotation before generating an invoice.');
        }

        if ($quotation->invoice()->exists()) {
            return back()->with('error', 'Invoice has already been generated.');
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

        DB::transaction(function () use ($quotation, $data) {

            $invoice = Invoice::create([
                'invoice_number' => trim($data['invoice_number']),
                'other_reference' => $data['other_reference'] ?? null,
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

            $customer = $quotation->customer;
            $newBalance = $customer->currentBalance() + (float) $quotation->total_amount;

            CustomerLedger::create([
                'customer_id' => $customer->id,
                'transaction_date' => $invoice->invoice_date,
                'amount' => $quotation->total_amount,
                'description' => 'Invoice ' . $invoice->invoice_number,
                'reference_type' => 'invoice',
                'reference_id' => $invoice->id,
                'entered_by' => Auth::id(),
                'balance_after' => $newBalance,
            ]);
        });

        return redirect()->route('quotations.show', $quotation)
            ->with('success', 'Invoice generated successfully. You can now download the invoice PDF or create a delivery challan.');
     }
    public function reject(Quotation $quotation)
    {
        $this->authorizeAccess($quotation);

        if (! $quotation->isEditable()) {
            return back()->with('error', 'Only newly created quotations can be rejected.');
        }

        $quotation->update(['status' => 'rejected']);

        return redirect()->route('quotations.show', $quotation)->with('success', 'Quotation rejected successfully.');
    }
    /**
     * Create a new draft quotation copied from this one (same customer, GST setting, and line items).
     */
    public function duplicate(Quotation $quotation)
    {
        $this->authorizeAccess($quotation);
        $quotation->load('items');

        $newQuotation = DB::transaction(function () use ($quotation) {
            $copy = Quotation::create([
                'quotation_number' => NumberSetting::generateNext('quotation'),
                'customer_id' => $quotation->customer_id,
                'user_id' => Auth::id(),
                'quotation_date' => now()->toDateString(),
                'status' => 'draft',
                'gst_applicable' => $quotation->gst_applicable,
                'discount_amount' => $quotation->discount_amount,
                'admin_charges' => $quotation->admin_charges,
                'material_handling_charges' => $quotation->material_handling_charges,
            ]);

            foreach ($quotation->items as $item) {
                QuotationItem::create([
                    'quotation_id' => $copy->id,
                    'product_id' => $item->product_id,
                    'despatch_to' => $item->despatch_to,
                    'size_mtr' => $item->size_mtr,
                    'no_of_rolls' => $item->no_of_rolls,
                    'total_mtr' => $item->total_mtr,
                    'price_per_mtr' => $item->price_per_mtr,
                    'amount' => $item->amount,
                ]);
            }

            $copy->recalculateTotals();

            return $copy;
        });

        return redirect()->route('quotations.index')
            ->with('success', "Quotation duplicated as {$newQuotation->quotation_number} (Quotation Created). Edit it any time before sending.");
    }

    /**
     * AJAX: return the last price charged to this customer for this product.
     */
    public function lastPrice(Request $request)
    {
        $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'product_id' => ['required', 'exists:products,id'],
        ]);

        $item = QuotationItem::query()
            ->where('product_id', $request->product_id)
            ->whereHas('quotation', function ($q) use ($request) {
                $q->where('customer_id', $request->customer_id)
                    ->where('status', 'approved');
            })
            ->join('quotations', 'quotations.id', '=', 'quotation_items.quotation_id')
            ->orderByDesc('quotations.approved_at')
            ->select('quotation_items.*')
            ->first();

        return response()->json([
            'found' => (bool) $item,
            'price_per_mtr' => $item ? (float) $item->price_per_mtr : null,
        ]);
    }

    private function authorizeAccess(Quotation $quotation): void
    {
        $user = Auth::user();
        if (! $user->isSuperAdmin() && $quotation->user_id !== $user->id) {
            abort(403, 'You do not have permission to access this quotation.');
        }
    }

    /**
     * Compute the gross sub total (before discount) from the raw submitted items array.
     * Used to validate that discount_amount never exceeds the sub total.
     */
   private function calculateItemsSubTotal(array $items): float
    {
        $subTotal = 0.0;

        foreach ($items as $item) {
            $noOfRolls = (int) ($item['no_of_rolls'] ?? 0);
            $pricePerMtr = (float) ($item['price_per_mtr'] ?? 0);
            $subTotal += $noOfRolls * $pricePerMtr;   // was: $sizeMtr * $noOfRolls * $pricePerMtr
        }

        return $subTotal;
    }

    private function syncItems(Quotation $quotation, array $items): void
    {
        foreach ($items as $item) {
            $sizeMtr = (float) $item['size_mtr'];
            $noOfRolls = (int) $item['no_of_rolls'];
            $pricePerMtr = (float) $item['price_per_mtr'];
            $totalMtr = $sizeMtr * $noOfRolls;      // keep — still shown as "Total Mtr" in views/PDF
            $amount = $noOfRolls * $pricePerMtr;    // was: $totalMtr * $pricePerMtr


            QuotationItem::create([
                'quotation_id' => $quotation->id,
                'product_id' => $item['product_id'],
                'despatch_to' => $item['despatch_to'] ?? null,
                'size_mtr' => $sizeMtr,
                'no_of_rolls' => $noOfRolls,
                'total_mtr' => $totalMtr,
                'price_per_mtr' => $pricePerMtr,
                'amount' => $amount,
            ]);
        }
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'quotation_date' => ['required', 'date'],
            'gst_applicable' => ['nullable', 'boolean'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'admin_charges' => ['nullable', 'numeric', 'min:0'],
            'material_handling_charges' => ['nullable', 'numeric', 'min:0'],
            'shipping_address_different' => ['nullable', 'boolean'],
            'shipping_address' => ['required', 'string', 'max:2000'],
            'shipping_address_line_2' => ['nullable', 'string', 'max:2000'],
            'shipping_state' => ['required', 'string', 'in:' . implode(',', config('states'))],
            'shipping_city' => ['required', 'string', 'max:100'],
            'shipping_pincode' => ['required', 'digits:6'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.despatch_to' => ['nullable', 'string', 'max:255'],
            'items.*.size_mtr' => ['required', 'numeric', 'min:0.01'],
            'items.*.no_of_rolls' => ['required', 'integer', 'min:1'],
            'items.*.price_per_mtr' => ['required', 'numeric', 'min:0'],
        ]);
    }
    private function shippingData(array $data): array
    {
        return [
            'shipping_address_different' => (bool) ($data['shipping_address_different'] ?? false),
            'shipping_address' => $data['shipping_address'],
            'shipping_address_line_2' => $data['shipping_address_line_2'] ?? null,
            'shipping_state' => $data['shipping_state'],
            'shipping_city' => $data['shipping_city'],
            'shipping_pincode' => $data['shipping_pincode'],
        ];
    }
}
