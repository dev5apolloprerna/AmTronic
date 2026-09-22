@php $po=$purchaseOrder??null;
$rows=old('items',$po?->items->map(fn($i)=>['product_id'=>$i->product_id,'quantity'=>$i->quantity,'rate'=>$i->rate])->all()
?? [['product_id'=>'','quantity'=>1,'rate'=>'']]); @endphp
<div class="form-row">
    <div class="form-group"><label>PO Number *</label><input class="form-control" name="po_number"
            value="{{ old('po_number',$po->po_number??('PO-'.date('Ymd-His'))) }}" required></div>
    <div class="form-group"><label>Vendor *</label><select id="vendor_id" class="form-control" name="vendor_id"
            required>
            <option value="">Select vendor</option>@foreach($vendors as $vendor)<option value="{{ $vendor->id }}"
                @selected(old('vendor_id',$po->vendor_id??'')==$vendor->id)>{{ $vendor->name }}</option>@endforeach
        </select></div>
    <div class="form-group"><label>Order Date *</label><input type="date" class="form-control" name="order_date"
            value="{{ old('order_date',$po?->order_date?->format('Y-m-d')??date('Y-m-d')) }}" required></div>
</div>
<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>Product</th>
                <th>Qty</th>
                <th>Rate</th>
                <th>Previous Buy Rate</th>
                <th>Amount</th>
                <th></th>
            </tr>
        </thead>
        <tbody id="po-items">
            @foreach($rows as $i=>$row)<tr>
                <td><select class="form-control product" name="items[{{ $i }}][product_id]" required>
                        <option value="">Select product</option>@foreach($products as $product)<option
                            value="{{ $product->id }}" @selected(($row['product_id']??'')==$product->id)>{{
                            $product->name }} ({{ $product->unit }})</option>@endforeach
                    </select></td>
                <td><input class="form-control qty" type="number" min="0.001" step="0.001"
                        name="items[{{ $i }}][quantity]" value="{{ $row['quantity']??1 }}" required></td>
                <td><input class="form-control rate" type="number" min="0" step="0.01" name="items[{{ $i }}][rate]"
                        value="{{ $row['rate']??'' }}" required></td>
                <td class="previous-rate text-muted">Select vendor and product</td>
                <td class="line-total">0.00</td>
                <td><button type="button" class="btn btn-danger btn-sm remove-row">×</button></td>
            </tr>@endforeach
        </tbody>
    </table>
</div><button type="button" id="add-row" class="btn btn-secondary btn-sm">+ Add Product</button>
<div class="form-group" style="margin-top:16px"><label>Notes</label><textarea class="form-control"
        name="notes">{{ old('notes',$po->notes??'') }}</textarea></div>
<template id="row-template">
    <tr>
        <td><select class="form-control product" required>
                <option value="">Select product</option>@foreach($products as $product)<option
                    value="{{ $product->id }}">{{ $product->name }} ({{ $product->unit }})</option>@endforeach
            </select></td>
        <td><input class="form-control qty" type="number" min="0.001" step="0.001" value="1" required></td>
        <td><input class="form-control rate" type="number" min="0" step="0.01" required></td>
        <td class="previous-rate text-muted">Select vendor and product</td>
        <td class="line-total">0.00</td>
        <td><button type="button" class="btn btn-danger btn-sm remove-row">×</button></td>
    </tr>
</template>
@push('scripts')
<script>
    (function () {
        const body = document.getElementById('po-items'), vendor = document.getElementById('vendor_id');
        function rename() {[...body.rows].forEach((r, i) => {r.querySelector('.product').name = `items[${i}][product_id]`; r.querySelector('.qty').name = `items[${i}][quantity]`; r.querySelector('.rate').name = `items[${i}][rate]`;});}
        function total(r) {r.querySelector('.line-total').textContent = ((+r.querySelector('.qty').value || 0) * (+r.querySelector('.rate').value || 0)).toFixed(2);}
        async function previous(r) {const p = r.querySelector('.product').value, v = vendor.value, out = r.querySelector('.previous-rate'); if (!p || !v) {out.textContent = 'Select vendor and product'; return;} out.textContent = 'Loading…'; const res = await fetch(`{{ route('purchase-orders.last-rate') }}?vendor_id=${v}&product_id=${p}`); const d = await res.json(); out.textContent = d.rate !== null ? `₹${Number(d.rate).toFixed(2)} (${d.order_date})` : 'No previous purchase';}
        body.addEventListener('input', e => {if (e.target.matches('.qty,.rate')) total(e.target.closest('tr'));}); body.addEventListener('change', e => {if (e.target.matches('.product')) previous(e.target.closest('tr'));}); body.addEventListener('click', e => {if (e.target.matches('.remove-row') && body.rows.length > 1) {e.target.closest('tr').remove(); rename();} }); vendor.addEventListener('change', () => [...body.rows].forEach(previous)); document.getElementById('add-row').onclick = () => {body.append(document.getElementById('row-template').content.cloneNode(true)); rename();};[...body.rows].forEach(r => {total(r); if (r.querySelector('.product').value) previous(r);});
    })();
</script>@endpush