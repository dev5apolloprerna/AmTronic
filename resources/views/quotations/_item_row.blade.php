@php
    $item = $item ?? null;
    $selectedKey = $item?->item_key;
@endphp
<div class="item-row">
    <div class="form-group">
        <label>Product / Material</label>
        <select name="items[{{ $index }}][item]" class="form-control js-item" required>
            <option value="">Select product or material</option>
            @if($products->isNotEmpty())
                <optgroup label="Products">
                    @foreach($products as $product)
                        <option value="product:{{ $product->id }}" data-description="{{ $product->description }}" {{ $selectedKey === 'product:' . $product->id ? 'selected' : '' }}>
                            {{ $product->name }} ({{ $product->unit }}){{ $product->status === 'inactive' ? ' - inactive' : '' }}
                        </option>
                    @endforeach
                </optgroup>
            @endif
            @if($materials->isNotEmpty())
                <optgroup label="Materials">
                    @foreach($materials as $material)
                        <option value="material:{{ $material->id }}" data-description="{{ $material->description }}" {{ $selectedKey === 'material:' . $material->id ? 'selected' : '' }}>
                            {{ $material->name }} ({{ $material->unit }}){{ $material->status === 'inactive' ? ' - inactive' : '' }}
                        </option>
                    @endforeach
                </optgroup>
            @endif
        </select>
        <div class="form-hint js-last-price-hint" style="display:none;"></div>
    </div>
    <div class="form-group">
        <label>Description</label>
        <textarea name="items[{{ $index }}][description]" class="form-control js-description" rows="2" maxlength="1000" placeholder="Optional details shown on the quotation">{{ $item?->description }}</textarea>
    </div>
    <div class="form-group">
        <label>Qty</label>
        <input type="number" step="0.01" min="0.01" name="items[{{ $index }}][qty]" class="form-control js-qty" value="{{ $item?->qty_label }}" required>
    </div>
    <div class="form-group">
        <label>Rate (&#8377;)</label>
        <input type="number" step="0.01" min="0" name="items[{{ $index }}][rate]" class="form-control js-rate" value="{{ $item ? number_format($item->rate, 2, '.', '') : '' }}" required>
    </div>
    <div class="form-group">
        <label>Amount (&#8377;)</label>
        <div class="form-control js-amount" style="background:#f8fafc;">0.00</div>
    </div>
    @if($item && $item->isLegacyRollLine())
        {{-- A line made before the change: keep its roll size so editing the draft does not lose it. --}}
        <input type="hidden" name="items[{{ $index }}][size_mtr]" value="{{ $item->size_mtr }}">
    @endif
    <button type="button" class="item-remove-btn js-remove" title="Remove line">&times;</button>
</div>
