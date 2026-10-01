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
use Illuminate\Support\Facades\URL;
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
          $customerId = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
        ])['customer_id'];

        $this->applyCustomerShippingDefaults($request, Customer::findOrFail($customerId));


        $usingDraftItems = $request->integer('quotation_id') === 0 && ! $request->has('items');
        if ($usingDraftItems) {
            $draftItems = $this->draftItems($request)->get();

            $request->merge([
                'items' => $draftItems->map(fn (QuotationItem $item) => [
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'qty' => $item->qty,
                    'rate' => $item->rate,
                ])->all(),
            ]);
        }

        $data = $this->validated($request);
        $quotation = DB::transaction(function () use ($request, $data, $usingDraftItems) {
            $attributes = collect($this->attributes($data))->except('quotation_id')->all() + [
                'quotation_number' => NumberSetting::generateNext('quotation'),
                'is_temporary' => false,
            ];

           $quotation = Quotation::create($attributes + [
                'user_id' => $request->user()->id,
                'status' => 'draft',
            ]);
            if ($usingDraftItems) {
                $this->draftItems($request)->update([
                    'quotation_id' => $quotation->id,
                    'user_id' => null,
                ]);
            } else {
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
        /** Persist products without creating a quotation master until final submit. */
    public function storeItem(Request $request)
    {
        $data = $this->validatedItem($request, true);
        $quotationId = (int) $data['quotation_id'];

        if ($quotationId === 0) {
            $item = QuotationItem::create([
                'quotation_id' => 0,
                'user_id' => $request->user()->id,
            ] + $this->itemAttributes($data));

            return response()->json([
                'message' => 'Product saved before quotation completion.',
                'quotation_id' => 0,
                'item_id' => $item->id,
                'data' => $this->draftDetail($request),
            ], 201);
        }

        $quotation = Quotation::findOrFail($quotationId);
        $this->owned($request, $quotation);

        if (! $quotation->isEditable()) {
            return $this->itemNotEditableResponse();
        }

        $item = DB::transaction(function () use ($quotation, $data) {
            $item = $quotation->items()->create($this->itemAttributes($data));

            $quotation->recalculateTotals();
            return $item;
        });

        return response()->json([
            'message' => 'Quotation product added successfully.',
            'quotation_id' => $quotation->id,
            'item_id' => $item->id,
            'data' => $this->detail($quotation),
        ], 201);
    }
    /** Return quotation products in the same shape as the item mutation endpoints. */
    public function productList(Request $request)
    {
        $data = $request->validate([
            'quotation_id' => ['required', 'integer', 'min:0'],
        ]);
        $quotationId = (int) $data['quotation_id'];

        if ($quotationId === 0) {
            return response()->json([
                'message' => 'Quotation products retrieved successfully.',
                'quotation_id' => 0,
                'data' => $this->draftDetail($request),
            ]);
        }

        $quotation = Quotation::findOrFail($quotationId);
        $this->owned($request, $quotation);

        return response()->json([
            'message' => 'Quotation products retrieved successfully.',
            'quotation_id' => $quotation->id,
            'data' => $this->detail($quotation),
        ]);
    }


    public function updateItem(Request $request)
    {
         $ids = $request->validate([
            'quotation_id' => ['required', 'integer', 'min:0'],
            'item_id' => ['required', 'integer'],
        ]);


         if ((int) $ids['quotation_id'] === 0) {
            $item = $this->draftItems($request)->findOrFail($ids['item_id']);
            $data = $this->validatedItem($request);
            $item->update($this->itemAttributes($data));

            return response()->json([
                'message' => 'Quotation product updated successfully.',
                'quotation_id' => 0,
                'item_id' => $item->id,
                'data' => $this->draftDetail($request),
            ]);
        }


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
            'quotation_id' => ['required', 'integer', 'min:0'],
            'item_id' => ['required', 'integer'],
        ]);

        if ((int) $ids['quotation_id'] === 0) {
            $this->draftItems($request)->findOrFail($ids['item_id'])->delete();

            return response()->json([
                'message' => 'Quotation product deleted successfully.',
                'quotation_id' => 0,
                'data' => $this->draftDetail($request),
            ]);
        }


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
                    'pdf_url' => self::documentUrl('api.quotations.pdf', ['quotation' => $quotation], $quotation),
                ],
                'invoice' => $invoice ? [
                    'id' => $invoice->id,
                    'number' => $invoice->invoice_number,
                    'status' => $invoice->document_status === 'invoice_approved' ? 'Invoice Sent' : 'Invoice Ready',
                    'status_code' => $invoice->document_status,
                    'pdf_url' => self::documentUrl('api.invoices.pdf', ['invoice' => $invoice], $quotation),
                ] : null,
                'delivery_challan' => $deliveryChallan ? [
                    'id' => $deliveryChallan->id,
                    'number' => $deliveryChallan->challan_number,
                    'status' => 'Delivery Challan Ready',
                    'status_code' => 'delivery_challan_ready',
                    'pdf_url' => self::documentUrl('api.delivery-challans.pdf', ['deliveryChallan' => $deliveryChallan], $quotation),
                ] : null,
            ],
        ];
    }

   private static function documentUrl(string $route, array $parameters, Quotation $quotation): string
    {
        return URL::signedRoute($route, $parameters + ['user' => $quotation->user_id]);
    }


    private function detail(Quotation $quotation): array
    {
        $quotation->load(['customer', 'items.product', 'invoice.deliveryChallan']);
        $quotationData = $quotation->toArray();
        $quotationData['items'] = $quotation->items->map(fn (QuotationItem $item) => [
            'id' => $item->id,
            'quotation_id' => $item->quotation_id,
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

    private function validatedItem(Request $request, bool $quotationIdRequired = false): array
    {
        return $request->validate([
            'product_id' => ['required', Rule::exists('products', 'id')->where('status', 'active')],
            'description' => ['nullable', 'string', 'max:1000'],
            'qty' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'rate' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'quotation_id' => [$quotationIdRequired ? 'required' : 'nullable', 'integer', 'min:0'],
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
    private function draftItems(Request $request)
    {
        return QuotationItem::where('quotation_id', 0)
            ->where('user_id', $request->user()->id)
            ->orderBy('id');
    }

    private function draftDetail(Request $request): array
    {
        $items = $this->draftItems($request)->with('product')->get();
        $subTotal = round((float) $items->sum('amount'), 2);

        return [
            'id' => 0,
            'total_amount' => $subTotal,
            'quotation' => [
                'id' => 0,
                'items' => $items->map(fn (QuotationItem $item) => [
                    'id' => $item->id,
                    'quotation_id' => 0,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product?->name,
                    'description' => $item->description,
                    'qty' => (float) $item->qty,
                    'rate' => (float) $item->rate,
                    'amount' => (float) $item->amount,
                ])->values()->all(),
                'sub_total' => number_format($subTotal, 2, '.', ''),
                'total_amount' => number_format($subTotal, 2, '.', ''),
            ],
        ];
    }


    private function validated(Request $request, ?Quotation $quotation = null): array
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'], 'quotation_date' => ['required', 'date'],
            'gst_applicable' => ['required', 'boolean'], 'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'admin_charges' => ['nullable', 'numeric', 'min:0'], 'material_handling_charges' => ['nullable', 'numeric', 'min:0'],
            'shipping_address_different' => ['nullable', 'boolean'], 'shipping_address' => ['nullable', 'string', 'max:2000'],
            'shipping_address_line_2' => ['nullable', 'string', 'max:2000'],
            'shipping_state' => ['nullable', 'string', Rule::in(State::selectableNames($quotation?->shipping_state))],
            'shipping_city' => ['nullable', 'string', 'max:100'], 'shipping_pincode' => ['nullable', 'digits:6'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where('status', 'active')],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.qty' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'items.*.rate' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'quotation_id' => ['nullable', 'integer', 'min:0'],
        ]);
        $subtotal = collect($data['items'])->sum(fn ($item) => $item['qty'] * $item['rate']);
        if (($data['discount_amount'] ?? 0) > $subtotal) {
            abort(response()->json(['message' => 'The discount amount cannot exceed the subtotal.', 'errors' => ['discount_amount' => ['The discount amount cannot exceed the subtotal.']]], 422));
        }
        return $data;
    }
     /** Fill omitted or blank create-request shipping fields from the selected customer. */
    private function applyCustomerShippingDefaults(Request $request, Customer $customer): void
    {
        $defaults = [
            'shipping_address' => $customer->address,
            'shipping_address_line_2' => $customer->address_line_2,
            'shipping_state' => $customer->state,
            'shipping_city' => $customer->city,
            'shipping_pincode' => $customer->pincode,
        ];

        $request->merge(collect($defaults)
            ->filter(fn (mixed $default, string $field) => blank($request->input($field)))
            ->all());
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
