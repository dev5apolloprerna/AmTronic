@php
    $s = $state ?? null;
    $locked = $lockReason ?? null;
@endphp

@if($locked)
    <div class="form-hint" style="margin-bottom:14px;">{{ $locked }}</div>
@endif

<div class="form-row">
    <div class="form-group">
        <label for="name">State Name *</label>
        <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $s->name ?? '') }}" maxlength="100" required {{ $locked ? 'readonly' : '' }}>
    </div>
    <div class="form-group">
        <label for="status">Status *</label>
        <select class="form-control" id="status" name="status" required {{ $locked ? 'disabled' : '' }}>
            <option value="active" {{ old('status', $s->status ?? 'active') === 'active' ? 'selected' : '' }}>Active</option>
            <option value="inactive" {{ old('status', $s->status ?? '') === 'inactive' ? 'selected' : '' }}>Inactive</option>
        </select>
    </div>
</div>
