@php $p = $product ?? null; @endphp

<div class="form-row">
    <div class="form-group">
        <label for="name">Product Name *</label>
        <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $p->name ?? '') }}" required>
    </div>
    <div class="form-group">
        <label for="code">Product Code</label>
        <input type="text" class="form-control" id="code" name="code" value="{{ old('code', $p->code ?? '') }}">
    </div>
</div>

<div class="form-row">
    <div class="form-group">
        <label for="unit">Unit *</label>
        <input type="text" class="form-control" id="unit" name="unit" value="{{ old('unit', $p->unit ?? 'Mtr') }}" required>
    </div>
    <div class="form-group">
        <label for="hsn_code">HSN Code</label>
        <input type="text" class="form-control" id="hsn_code" name="hsn_code" inputmode="numeric" pattern="[0-9]{5,6}" minlength="5" maxlength="6" value="{{ old('hsn_code', $p->hsn_code ?? '') }}" required>
    </div>
    <div class="form-group">
        <label for="status">Status *</label>
        <select class="form-control" id="status" name="status" required>
            <option value="active" {{ old('status', $p->status ?? 'active') === 'active' ? 'selected' : '' }}>Active</option>
            <option value="inactive" {{ old('status', $p->status ?? '') === 'inactive' ? 'selected' : '' }}>Inactive</option>
        </select>
    </div>
</div>

<div class="form-group">
    <label for="description">Description</label>
    <textarea class="form-control" id="description" name="description">{{ old('description', $p->description ?? '') }}</textarea>
</div>
