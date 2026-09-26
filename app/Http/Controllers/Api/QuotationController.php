<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\NumberSetting;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\State;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class QuotationController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'status' => ['nullable', Rule::in(['created', 'sent', 'approved', 'rejected', 'invoice_sent'])],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $status = $request->input('status');
        $search = $request->input('search');
        $quotations = Quotation::with(['customer', 'invoice.deliveryChallan'])
            ->where('user_id', $request->user()->id)
            ->where('is_temporary', false)
            ->when($search, fn ($q) => $q->where(fn ($qq) => $qq
                ->where('quotation_number', 'like', "%{$search}%")
                ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$search}%"))))
            ->when($status === 'created', fn ($q) => $q->where('status', 'draft')->where('document_status', '!=', 'quotation_sent'))
            ->when($status === 'sent', fn ($q) => $q->where('status', 'draft')->where('document_status', 'quotation_sent'))
            ->when(in_array($status, ['approved', 'rejected'], true), fn ($q) => $q->where('status', $status))
            ->when($status === 'invoice_sent', fn ($q) => $q->whereHas('invoice', fn ($invoice) => $invoice->where('document_status', 'invoice_approved')))
            ->latest()
            ->get()
            ->map(fn (Quotation $quotation) => self::summary($quotation));

        return response()->json(['data' => $quotations]);
    }

       public function pending(Request $request)
    {
        return $this->listForStatus($request, 'pending');
    }

    public function approved(Request $request)
    {
        return $this->listForStatus($request, 'approved');
    }

    public function rejected(Request $request)
    {
        return $this->listForStatus($request, 'rejected');
    }

    private function listForStatus(Request $request, string $status)
    {
        $request->validate(['search' => ['nullable', 'string', 'max:255']]);
        $search = $request->input('search');
        $quotations = Quotation::with(['customer', 'invoice.deliveryChallan'])
            ->where('user_id', $request->user()->id)
            ->where('is_temporary', false)
            ->when($status === 'pending', fn ($query) => $query->where('status', 'draft'))
            ->when($status !== 'pending', fn ($query) => $query->where('status', $status))
            ->when($search, fn ($query) => $query->where(fn ($nested) => $nested
                ->where('quotation_number', 'like', "%{$search}%")
                ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$search}%"))))
            ->latest()
            ->get()
            ->map(fn (Quotation $quotation) => self::summary($quotation));

        return response()->json(['data' => $quotations]);
    }


    public function lookups()
    {
        return response()->json(['data' => [
            'customers' => Customer::orderBy('name')->get(),
            'products' => Product::where('status', 'active')->orderBy('name')->get(),
            'states' => State::selectableNames(),
        ]]);
    }

    public function store(Request $request)
    {
         $temporaryQuotation = null;
        if ($request->filled('quotation_id')) {
            $temporaryQuotation = Quotation::findOrFail($request->integer('quotation_id'));
            $this->owned($request, $temporaryQuotation);
            abort_unless($temporaryQuotation->is_temporary, 409, 'This quotation has already been saved.');
        }

        $usingSavedItems = $temporaryQuotation && ! $request->has('items');
        if ($usingSavedItems) {
            $request->merge([
                'items' => $temporaryQuotation->items()->get()->map(fn (QuotationItem $item) => [
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'qty' => $item->qty,
                    'rate' => $item->rate,
                ])->all(),
            ]);
        }

        $data = $this->validated($request);
        $quotation = DB::transaction(function () use ($request, $data, $temporaryQuotation, $usingSavedItems) {
            $attributes = collect($this->attributes($data))->except('quotation_id')->all() + [
                'quotation_number' => NumberSetting::generateNext('quotation'),
                'is_temporary' => false,
            ];

            if ($temporaryQuotation) {
                $quotation = $temporaryQuotation;
                $quotation->update($attributes);
                if (! $usingSavedItems) {
                    $quotation->items()->delete();
                    $this->syncItems($quotation, $data['items']);
                }
            } else {
                $quotation = Quotation::create($attributes + [
                    'user_id' => $request->user()->id,
                    'status' => 'draft',
                ]);
                $this->syncItems($quotation, $data['items']);
            }
            $quotation->recalculateTotals();

            return $quotation;
        });

        return response()->json(['message' => 'Quotation created successfully.', 'data' => $this->detail($quotation)], 201);
    }

    public function show(Request $request, Quotation $quotation)
    {
        $this->owned($request, $quotation);
        return response()->json(['data' => $this->detail($quotation)]);
    }

    public function update(Request $request, Quotation $quotation)
    {
        $this->owned($request, $quotation);
        if (! $quotation->isEditable()) {
            return response()->json(['message' => 'Sent or approved quotations cannot be edited.'], 409);
        }

        $data = $this->validated($request, $quotation);
        DB::transaction(function () use ($quotation, $data) {
            $quotation->update($this->attributes($data));
            $quotation->items()->delete();
            $this->syncItems($quotation, $data['items']);
            $quotation->recalculateTotals();
        });

        return response()->json(['message' => 'Quotation updated successfully.', 'data' => $this->detail($quotation)]);
    }

    public function destroy(Request $request, Quotation $quotation)
    {

        $this->owned($request, $quotation);
        if (! $quotation->isEditable()) {
            return response()->json(['message' => 'Sent or approved quotations cannot be deleted.'], 409);
        }
        $quotation->delete();
        return response()->json(['message' => 'Quotation deleted successfully.']);
    }
    /**
     * Persist one product as soon as the employee taps "Add New Product".
     * This intentionally does not wait for the complete quotation update, so
     * an interrupted mobile session can be restored with show().
     */
    public function storeItem(Request $request)
    {
        $data = $this->validatedStoreItems($request);
        $quotation = null;

        if (filled($data['quotation_id'] ?? null)) {
            $quotation = Quotation::findOrFail($data['quotation_id']);
        }

        if ($quotation) {
            $this->owned($request, $quotation);
        } else {
            $quotation = Quotation::firstOrCreate([
                'user_id' => $request->user()->id,
                'is_temporary' => true,
            ], [
                'quotation_number' => null,
                'customer_id' => null,
                'quotation_date' => null,
                'status' => 'draft',
            ]);
        }
        if (! $quotation->isEditable()) {
            return $this->itemNotEditableResponse();
        }

        $items = DB::transaction(function () use ($quotation, $data) {
            $items = collect($data['items'])->map(
                fn (array $item) => $quotation->items()->create($this->itemAttributes($item)),
            );
            $quotation->recalculateTotals();
            return $items;
        });

        return response()->json([
            'message' => $quotation->is_temporary ? 'Products saved before quotation completion.' : 'Quotation products added successfully.',
            'quotation_id' => $quotation->id,
            'item_ids' => $items->pluck('id')->all(),
            'data' => $this->detail($quotation),
        ], 201);
    }

    public function updateItem(Request $request)
    {
         $ids = $request->validate([
            'quotation_id' => ['required', 'integer', 'exists:quotations,id'],
            'item_id' => ['required', 'integer', 'exists:quotation_items,id'],
        ]);
        $quotation = Quotation::findOrFail($ids['quotation_id']);
        $item = QuotationItem::findOrFail($ids['item_id']);

        $this->ownedItem($request, $quotation, $item);
        if (! $quotation->isEditable()) {
            return $this->itemNotEditableResponse();
        }

        $data = $this->validatedItem($request);
        DB::transaction(function () use ($quotation, $item, $data) {
            $item->update($this->itemAttributes($data));
            $quotation->recalculateTotals();
        });

        return response()->json([
            'message' => 'Quotation product updated successfully.',
            'item_id' => $item->id,
            'data' => $this->detail($quotation),
        ]);
    }

    public function destroyItem(Request $request)
    {
        $ids = $request->validate([
            'quotation_id' => ['required', 'integer', 'exists:quotations,id'],
            'item_id' => ['required', 'integer', 'exists:quotation_items,id'],
        ]);
        $quotation = Quotation::findOrFail($ids['quotation_id']);
        $item = QuotationItem::findOrFail($ids['item_id']);

        $this->ownedItem($request, $quotation, $item);
        if (! $quotation->isEditable()) {
            return $this->itemNotEditableResponse();
        }

        DB::transaction(function () use ($quotation, $item) {
            $item->delete();
            $quotation->recalculateTotals();
        });

        return response()->json([
            'message' => 'Quotation product deleted successfully.',
            'data' => $this->detail($quotation),
        ]);
    }


    public function markSent(Request $request, Quotation $quotation)
    {
        $this->owned($request, $quotation);
        if (! $quotation->isEditable()) {
            return response()->json(['message' => 'Only newly created quotations can be sent.'], 409);
        }
        $quotation->update(['document_status' => 'quotation_sent']);
        return response()->json(['message' => 'Quotation marked as sent.', 'data' => $this->detail($quotation)]);
    }

    public function approve(Request $request, Quotation $quotation)
    {
        $this->owned($request, $quotation);
        if (! $quotation->isSent()) {
            return response()->json(['message' => $quotation->status === 'approved' ? 'Quotation is already approved.' : 'Send the quotation before approving it.'], 409);
        }
        $quotation->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
        return response()->json(['message' => 'Quotation approved successfully.', 'data' => $this->detail($quotation)]);
    }

    public function reject(Request $request, Quotation $quotation)
    {
        $this->owned($request, $quotation);
        if (! $quotation->isEditable()) {
            return response()->json(['message' => 'Only newly created quotations can be rejected.'], 409);
        }
        $quotation->update(['status' => 'rejected']);
        return response()->json(['message' => 'Quotation rejected successfully.', 'data' => $this->detail($quotation)]);
    }

    public static function summary(Quotation $quotation): array
    {
        $invoice = $quotation->invoice;
        $deliveryChallan = $invoice?->deliveryChallan;

        return [
            'id' => $quotation->id, 'quotation_number' => $quotation->quotation_number,
            'quotation_date' => $quotation->quotation_date?->toDateString(), 'customer' => $quotation->customer,
            'status' => $quotation->displayStatus(), 'status_code' => $quotation->displayStatusClass(),
            'total_amount' => (float) $quotation->total_amount, 'editable' => $quotation->isEditable(),
            'documents' => [
                'quotation' => [
                    'status' => $quotation->displayStatus(),
                    'status_code' => $quotation->displayStatusClass(),
                    'pdf_url' => route('api.quotations.pdf', $quotation),
                ],
                'invoice' => $invoice ? [
                    'id' => $invoice->id,
                    'number' => $invoice->invoice_number,
                    'status' => $invoice->document_status === 'invoice_approved' ? 'Invoice Sent' : 'Invoice Ready',
                    'status_code' => $invoice->document_status,
                    'pdf_url' => route('api.invoices.pdf', $invoice),
                ] : null,
                'delivery_challan' => $deliveryChallan ? [
                    'id' => $deliveryChallan->id,
                    'number' => $deliveryChallan->challan_number,
                    'status' => 'Delivery Challan Ready',
                    'status_code' => 'delivery_challan_ready',
                    'pdf_url' => route('api.delivery-challans.pdf', $deliveryChallan),
                ] : null,
            ],
        ];
    }

    private function detail(Quotation $quotation): array
    {
        $quotation->load(['customer', 'items.product', 'invoice.deliveryChallan']);
        $quotationData = $quotation->toArray();
        $quotationData['items'] = $quotation->items->map(fn (QuotationItem $item) => [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'product_name' => $item->product?->name,
            'description' => $item->description,
            'qty' => $item->qty,
            'rate' => $item->rate,
            'amount' => (float) $item->amount,
        ])->values()->all();

        return self::summary($quotation) + ['quotation' => $quotationData];
    }

    private function owned(Request $request, Quotation $quotation): void
    {
        abort_unless($quotation->user_id === $request->user()->id, 403, 'You do not have permission to access this quotation.');
    }
    private function ownedItem(Request $request, Quotation $quotation, QuotationItem $item): void
    {
        $this->owned($request, $quotation);
        abort_unless($item->quotation_id === $quotation->id, 404);
    }

    private function itemNotEditableResponse()
    {
        return response()->json(['message' => 'Products on sent, approved, or rejected quotations cannot be changed.'], 409);
    }

    private function validatedItem(Request $request): array
    {
        return $request->validate([
            'product_id' => ['required', Rule::exists('products', 'id')->where('status', 'active')],
            'description' => ['nullable', 'string', 'max:1000'],
            'qty' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'rate' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'quotation_id' => ['nullable', 'integer', 'exists:quotations,id'],

        ]);
    }

  private function validatedStoreItems(Request $request): array
    {
        return $request->validate([
            'quotation_id' => ['nullable', 'integer', 'exists:quotations,id'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where('status', 'active')],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.qty' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'items.*.rate' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
        ]);
    }

    private function itemAttributes(array $item): array
    {
        return [
            'product_id' => $item['product_id'],
            'description' => filled($item['description'] ?? null) ? trim($item['description']) : null,
            // The database retains its legacy column names; the API deliberately
            // exposes these values as qty and rate, matching the web quotation form.
            'no_of_rolls' => $item['qty'],
            'price_per_mtr' => $item['rate'],
            'amount' => round($item['qty'] * $item['rate'], 2),
        ];
    }

    private function validated(Request $request, ?Quotation $quotation = null): array
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'], 'quotation_date' => ['required', 'date'],
            'gst_applicable' => ['required', 'boolean'], 'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'admin_charges' => ['nullable', 'numeric', 'min:0'], 'material_handling_charges' => ['nullable', 'numeric', 'min:0'],
            'shipping_address_different' => ['nullable', 'boolean'], 'shipping_address' => ['required', 'string', 'max:2000'],
            'shipping_address_line_2' => ['nullable', 'string', 'max:2000'],
            'shipping_state' => ['required', 'string', Rule::in(State::selectableNames($quotation?->shipping_state))],
            'shipping_city' => ['required', 'string', 'max:100'], 'shipping_pincode' => ['required', 'digits:6'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where('status', 'active')],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.qty' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'items.*.rate' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'quotation_id' => ['nullable', 'integer', 'exists:quotations,id'],
        ]);
        $subtotal = collect($data['items'])->sum(fn ($item) => $item['qty'] * $item['rate']);
        if (($data['discount_amount'] ?? 0) > $subtotal) {
            abort(response()->json(['message' => 'The discount amount cannot exceed the subtotal.', 'errors' => ['discount_amount' => ['The discount amount cannot exceed the subtotal.']]], 422));
        }
        return $data;
    }

    private function attributes(array $data): array
    {
        return collect($data)->except('items')->all();
    }

    private function syncItems(Quotation $quotation, array $items): void
    {
        foreach ($items as $item) {
            $quotation->items()->create($this->itemAttributes($item));
        }
    }
}
