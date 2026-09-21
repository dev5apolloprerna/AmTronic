@php $m = $material ?? null; @endphp

<div class="form-row">
    <div class="form-group">
        <label for="name">Material Name *</label>
        <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $m->name ?? '') }}" required>
    </div>
    <div class="form-group">
        <label for="code">Material Code</label>
        <input type="text" class="form-control" id="code" name="code" value="{{ old('code', $m->code ?? '') }}">
    </div>
</div>

<div class="form-row">
    <div class="form-group">
        <label for="unit">Unit *</label>
        <input type="text" class="form-control" id="unit" name="unit" value="{{ old('unit', $m->unit ?? 'Nos') }}" required>
    </div>
    <div class="form-group">
        <label for="hsn_code">HSN Code *</label>
        <input type="text" class="form-control" id="hsn_code" name="hsn_code" inputmode="numeric" pattern="([0-9]{4}|[0-9]{6}|[0-9]{8})" minlength="4" maxlength="8" title="HSN code must be 4, 6 or 8 digits" value="{{ old('hsn_code', $m->hsn_code ?? '') }}" required>
    </div>
    <div class="form-group">
        <label for="status">Status *</label>
        <select class="form-control" id="status" name="status" required>
            <option value="active" {{ old('status', $m->status ?? 'active') === 'active' ? 'selected' : '' }}>Active</option>
            <option value="inactive" {{ old('status', $m->status ?? '') === 'inactive' ? 'selected' : '' }}>Inactive</option>
        </select>
    </div>
</div>

<div class="form-group">
    <label for="description">Description</label>
    <textarea class="form-control" id="description" name="description">{{ old('description', $m->description ?? '') }}</textarea>
</div>
