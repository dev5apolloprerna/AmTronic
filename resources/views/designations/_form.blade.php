@php $d = $designation ?? null; @endphp

<div class="form-row">
    <div class="form-group">
        <label for="name">Designation *</label>
        <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $d->name ?? '') }}" maxlength="100" required>
    </div>
    <div class="form-group">
        <label for="status">Status *</label>
        <select class="form-control" id="status" name="status" required>
            <option value="active" {{ old('status', $d->status ?? 'active') === 'active' ? 'selected' : '' }}>Active</option>
            <option value="inactive" {{ old('status', $d->status ?? '') === 'inactive' ? 'selected' : '' }}>Inactive</option>
        </select>
    </div>
</div>

<div class="form-group">
    <label class="checkbox-row">
        <input type="hidden" name="can_login" value="0">
        <input type="checkbox" name="can_login" value="1" {{ old('can_login', $d->can_login ?? false) ? 'checked' : '' }}>
        Employees with this designation can log in
    </label>
  <div class="form-hint">Un-ticking this stops existing employees with this designation from logging in.</div>
</div>

<div class="form-group">
    <label class="checkbox-row">
        <input type="hidden" name="api_only" value="0">
        <input type="checkbox" name="api_only" value="1" {{ old('api_only', $d->api_only ?? false) ? 'checked' : '' }}>
        API login only (for Sales Executives)
    </label>
    <div class="form-hint">API-only employees cannot use the admin website. They can use the mobile/API quotation workflow.</div>
</div>
