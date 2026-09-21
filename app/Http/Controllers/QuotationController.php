<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerLedger;
// use App\Models\DeliveryChallan;
use App\Models\Invoice;
use App\Models\Material;
use App\Models\NumberSetting;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\State;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
        ['products' => $products, 'materials' => $materials] = $this->itemChoices();

        return view('quotations.create', compact('customers', 'products', 'materials'));
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
        $quotation->load(['items.product', 'items.material', 'customer', 'user', 'approvedBy', 'invoice.payments']);

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
        'items.material',
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

        $quotation->load('items.product', 'items.material');
        $customers = Customer::orderBy('name')->get();
        ['products' => $products, 'materials' => $materials] = $this->itemChoices($quotation);

        return view('quotations.edit', compact('quotation', 'customers', 'products', 'materials'));
    }

    public function update(Request $request, Quotation $quotation)
    {
        $this->authorizeAccess($quotation);

        if (! $quotation->isEditable()) {
            return redirect()->route('quotations.show', $quotation)->with('error', 'Approved quotations cannot be edited.');
        }

        $data = $this->validateData($request, $quotation);

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
                    'material_id' => $item->material_id,
                    'description' => $item->description,
                    'despatch_to' => $item->despatch_to,
                    'size_mtr' => $item->size_mtr,
                    'no_of_rolls' => $item->getRawOriginal('no_of_rolls'),
                    'total_mtr' => $item->total_mtr,
                    'price_per_mtr' => $item->getRawOriginal('price_per_mtr'),
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
     * AJAX: the last rate charged to this customer for this item (a product or
     * a material), taken from their most recent approved quotation.
     */
    public function lastPrice(Request $request)
    {
        $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'item' => ['required', 'string'],
        ]);

        $parsed = QuotationItem::parseKey($request->item);

        $item = $parsed ? QuotationItem::query()
            ->where('quotation_items.' . $parsed[0] . '_id', $parsed[1])
            ->whereHas('quotation', function ($q) use ($request) {
                $q->where('customer_id', $request->customer_id)
                    ->where('status', 'approved');
            })
            ->join('quotations', 'quotations.id', '=', 'quotation_items.quotation_id')
            ->orderByDesc('quotations.approved_at')
            ->select('quotation_items.*')
            ->first() : null;

        return response()->json([
            'found' => (bool) $item,
            'rate' => $item ? $item->rate : null,
        ]);
    }

    /**
     * Products and Materials offered in the item dropdown: everything active,
     * plus anything already on $quotation, so editing a quotation whose item
     * was later made inactive does not silently drop it.
     *
     * @return array{products: \Illuminate\Support\Collection, materials: \Illuminate\Support\Collection}
     */
    private function itemChoices(?Quotation $quotation = null): array
    {
        $usedProducts = $quotation ? $quotation->items->pluck('product_id')->filter()->all() : [];
        $usedMaterials = $quotation ? $quotation->items->pluck('material_id')->filter()->all() : [];

        return [
            'products' => Product::where(fn ($q) => $q->where('status', 'active')->orWhereIn('id', $usedProducts))
                ->orderBy('name')->get(),
            'materials' => Material::where(fn ($q) => $q->where('status', 'active')->orWhereIn('id', $usedMaterials))
                ->orderBy('name')->get(),
        ];
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
            $subTotal += round((float) ($item['qty'] ?? 0) * (float) ($item['rate'] ?? 0), 2);
        }

        return $subTotal;
    }

    private function syncItems(Quotation $quotation, array $items): void
    {
        foreach ($items as $item) {
            [$type, $id] = QuotationItem::parseKey($item['item']);

            $qty = round((float) $item['qty'], 2);
            $rate = round((float) $item['rate'], 2);

            // Only lines made before the change carry a roll size; it is passed
            // through untouched so editing an old draft does not lose it.
            $sizeMtr = (float) ($item['size_mtr'] ?? 0) > 0 ? round((float) $item['size_mtr'], 2) : null;

            QuotationItem::create([
                'quotation_id' => $quotation->id,
                'product_id' => $type === QuotationItem::TYPE_PRODUCT ? $id : null,
                'material_id' => $type === QuotationItem::TYPE_MATERIAL ? $id : null,
                'description' => filled($item['description'] ?? null) ? trim($item['description']) : null,
                'size_mtr' => $sizeMtr,
                'no_of_rolls' => $qty,
                'total_mtr' => $sizeMtr !== null ? round($sizeMtr * $qty, 2) : null,
                'price_per_mtr' => $rate,
                'amount' => round($qty * $rate, 2),
            ]);
        }
    }

    private function validateData(Request $request, ?Quotation $quotation = null): array
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
            'shipping_state' => ['required', 'string', Rule::in(State::selectableNames($quotation?->shipping_state))],
            'shipping_city' => ['required', 'string', 'max:100'],
            'shipping_pincode' => ['required', 'digits:6'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item' => ['required', 'string', $this->itemRule($quotation)],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.qty' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'items.*.rate' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'items.*.size_mtr' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ]);
    }

    /**
     * The chosen item must be a real, ACTIVE product or material - or one that
     * is already on this quotation (so an old, since-deactivated line can stay).
     */
    private function itemRule(?Quotation $quotation): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($quotation) {
            $parsed = QuotationItem::parseKey($value);

            if ($parsed === null) {
                $fail('Select a product or material.');

                return;
            }

            [$type, $id] = $parsed;
            $model = $type === QuotationItem::TYPE_PRODUCT ? Product::find($id) : Material::find($id);

            if (! $model) {
                $fail('The selected ' . $type . ' no longer exists.');

                return;
            }

            $alreadyOnQuotation = $quotation && $quotation->items()->where($type . '_id', $id)->exists();

            if ($model->status !== 'active' && ! $alreadyOnQuotation) {
                $fail('"' . $model->name . '" is inactive and cannot be added to a quotation.');
            }
        };
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
