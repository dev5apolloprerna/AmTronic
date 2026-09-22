<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Vendor;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->get('search'));
        $orders = PurchaseOrder::with('vendor')->when($search, fn ($q) => $q->where(fn ($q) => $q
            ->where('po_number', 'like', "%{$search}%")
            ->orWhereHas('vendor', fn ($q) => $q->where('name', 'like', "%{$search}%"))))
            ->latest('order_date')->paginate(20)->withQueryString();
        return view('purchase-orders.index', compact('orders', 'search'));
    }

    public function create() { return view('purchase-orders.create', $this->formData()); }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $order = DB::transaction(function () use ($data) {
            $items = $data['items']; unset($data['items']);
            $data['created_by'] = auth()->id();
            $data['total_amount'] = collect($items)->sum(fn ($item) => round($item['quantity'] * $item['rate'], 2));
            $order = PurchaseOrder::create($data);
            foreach ($items as $item) $order->items()->create($item + ['amount' => round($item['quantity'] * $item['rate'], 2)]);
            return $order;
        });
        return redirect()->route('purchase-orders.show', $order)->with('success', 'Purchase order created successfully.');
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load(['vendor', 'items.product', 'creator']);
        return view('purchase-orders.show', compact('purchaseOrder'));
    }

    public function edit(PurchaseOrder $purchaseOrder) { $purchaseOrder->load('items'); return view('purchase-orders.edit', $this->formData() + compact('purchaseOrder')); }

    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        $data = $this->validated($request, $purchaseOrder);
        DB::transaction(function () use ($data, $purchaseOrder) {
            $items = $data['items']; unset($data['items']);
            $data['total_amount'] = collect($items)->sum(fn ($item) => round($item['quantity'] * $item['rate'], 2));
            $purchaseOrder->update($data); $purchaseOrder->items()->delete();
            foreach ($items as $item) $purchaseOrder->items()->create($item + ['amount' => round($item['quantity'] * $item['rate'], 2)]);
        });
        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('success', 'Purchase order updated successfully.');
    }

    public function destroy(PurchaseOrder $purchaseOrder) { $purchaseOrder->delete(); return redirect()->route('purchase-orders.index')->with('success', 'Purchase order deleted.'); }

    public function download(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load(['vendor', 'items.product']);
        return Pdf::loadView('purchase-orders.pdf', compact('purchaseOrder'))->setPaper('a4')->download($purchaseOrder->po_number.'.pdf');
    }

    public function lastRate(Request $request)
    {
        $data = $request->validate(['vendor_id' => ['required', 'exists:vendors,id'], 'product_id' => ['required', 'exists:products,id']]);
        $item = PurchaseOrderItem::where('product_id', $data['product_id'])->whereHas('purchaseOrder', fn ($q) => $q->where('vendor_id', $data['vendor_id']))
            ->with('purchaseOrder:id,order_date')->latest('id')->first();
        return response()->json(['rate' => $item?->rate, 'order_date' => $item?->purchaseOrder?->order_date?->format('Y-m-d')]);
    }

    private function formData(): array { return ['vendors' => Vendor::orderBy('name')->get(), 'products' => Product::where('status', 'active')->orderBy('name')->get()]; }
    private function validated(Request $request, ?PurchaseOrder $order = null): array
    {
        return $request->validate([
            'po_number' => ['required','string','max:100', Rule::unique('purchase_orders')->ignore($order)],
            'vendor_id' => ['required','exists:vendors,id'], 'order_date' => ['required','date'], 'notes' => ['nullable','string','max:2000'],
            'items' => ['required','array','min:1'], 'items.*.product_id' => ['required','distinct','exists:products,id'],
            'items.*.quantity' => ['required','numeric','gt:0'], 'items.*.rate' => ['required','numeric','gte:0'],
        ]);
    }
}
