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
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $status = $request->input('status');
        $search = $request->input('search');
        $quotations = Quotation::with(['customer', 'invoice'])
            ->where('user_id', $request->user()->id)
            ->when($search, fn ($q) => $q->where(fn ($qq) => $qq
                ->where('quotation_number', 'like', "%{$search}%")
                ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$search}%"))))
            ->when($status === 'created', fn ($q) => $q->where('status', 'draft')->where('document_status', '!=', 'quotation_sent'))
            ->when($status === 'sent', fn ($q) => $q->where('status', 'draft')->where('document_status', 'quotation_sent'))
            ->when(in_array($status, ['approved', 'rejected'], true), fn ($q) => $q->where('status', $status))
            ->when($status === 'invoice_sent', fn ($q) => $q->whereHas('invoice', fn ($invoice) => $invoice->where('document_status', 'invoice_approved')))
            ->latest()->paginate($request->integer('per_page', 15));

        $quotations->through(fn (Quotation $quotation) => self::summary($quotation));

        return response()->json($quotations);
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
        $data = $this->validated($request);
        $quotation = DB::transaction(function () use ($request, $data) {
            $quotation = Quotation::create($this->attributes($data) + [
                'quotation_number' => NumberSetting::generateNext('quotation'),
                'user_id' => $request->user()->id,
                'status' => 'draft',
            ]);
            $this->syncItems($quotation, $data['items']);
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
        return [
            'id' => $quotation->id, 'quotation_number' => $quotation->quotation_number,
            'quotation_date' => $quotation->quotation_date->toDateString(), 'customer' => $quotation->customer,
            'status' => $quotation->displayStatus(), 'status_code' => $quotation->displayStatusClass(),
            'total_amount' => (float) $quotation->total_amount, 'editable' => $quotation->isEditable(),
        ];
    }

    private function detail(Quotation $quotation): array
    {
        $quotation->load(['customer', 'items.product', 'invoice']);
        return self::summary($quotation) + ['quotation' => $quotation];
    }

    private function owned(Request $request, Quotation $quotation): void
    {
        abort_unless($quotation->user_id === $request->user()->id, 403, 'You do not have permission to access this quotation.');
    }

    private function validated(Request $request, ?Quotation $quotation = null): array
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'], 'quotation_date' => ['required', 'date'],
            'gst_applicable' => ['nullable', 'boolean'], 'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'admin_charges' => ['nullable', 'numeric', 'min:0'], 'material_handling_charges' => ['nullable', 'numeric', 'min:0'],
            'shipping_address_different' => ['nullable', 'boolean'], 'shipping_address' => ['required', 'string', 'max:2000'],
            'shipping_address_line_2' => ['nullable', 'string', 'max:2000'],
            'shipping_state' => ['required', 'string', Rule::in(State::selectableNames($quotation?->shipping_state))],
            'shipping_city' => ['required', 'string', 'max:100'], 'shipping_pincode' => ['required', 'digits:6'],
            'items' => ['required', 'array', 'min:1'], 'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.despatch_to' => ['nullable', 'string', 'max:255'], 'items.*.size_mtr' => ['required', 'numeric', 'min:0.01'],
            'items.*.no_of_rolls' => ['required', 'integer', 'min:1'], 'items.*.price_per_mtr' => ['required', 'numeric', 'min:0'],
        ]);
        $subtotal = collect($data['items'])->sum(fn ($item) => $item['no_of_rolls'] * $item['price_per_mtr']);
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
            QuotationItem::create($item + ['quotation_id' => $quotation->id,
                'total_mtr' => $item['size_mtr'] * $item['no_of_rolls'],
                'amount' => $item['no_of_rolls'] * $item['price_per_mtr']]);
        }
    }
}
