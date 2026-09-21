@php
    $item = $item ?? null;
@endphp
<div class="item-row">
    <div class="form-group">
        <label>Product</label>
        <select name="items[{{ $index }}][product_id]" class="form-control js-product" required>
            <option value="">Select product</option>
            @foreach($products as $product)
                <option value="{{ $product->id }}" {{ optional($item)->product_id == $product->id ? 'selected' : '' }}>
                    {{ $product->name }} ({{ $product->unit }})
                </option>
            @endforeach
        </select>
        <div class="form-hint js-last-price-hint" style="display:none;"></div>
    </div>
    <!-- <div class="form-group">
        <label>Despatch To</label>
        <input type="text" name="items[{{ $index }}][despatch_to]" class="form-control js-despatch" value="{{ optional($item)->despatch_to }}" placeholder="e.g. Jaipur">
    </div> -->
    <div class="form-group">
        <label>Size (Mtr)</label>
        <input type="number" step="0.01" min="0.01" name="items[{{ $index }}][size_mtr]" class="form-control js-size" value="{{ optional($item)->size_mtr }}" required>
    </div>
    <div class="form-group">
        <label># of Rolls</label>
        <input type="number" step="1" min="1" name="items[{{ $index }}][no_of_rolls]" class="form-control js-rolls" value="{{ optional($item)->no_of_rolls }}" required>
    </div>
    <div class="form-group">
        <label>Price / Roll (&#8377;)</label>
        <input type="number" step="0.01" min="0" name="items[{{ $index }}][price_per_mtr]" class="form-control js-price" value="{{ optional($item)->price_per_mtr }}" required>
    </div>
    <div class="form-group">
        <label>Amount (&#8377;)</label>
        <div class="form-control js-amount" style="background:#f8fafc;">0.00</div>
    </div>
    <button type="button" class="item-remove-btn js-remove" title="Remove line">&times;</button>
</div>
