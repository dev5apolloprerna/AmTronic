@php($vendor = $vendor ?? null)

<div class="form-row">
    <div class="form-group">
        <label for="name">Vendor Name *</label>
        <input id="name" name="name" class="form-control" value="{{ old('name', $vendor->name ?? '') }}" maxlength="255" required>
    </div>
    <div class="form-group">
        <label for="contact_person">Contact Person</label>
        <input id="contact_person" name="contact_person" class="form-control" value="{{ old('contact_person', $vendor->contact_person ?? '') }}" maxlength="255">
    </div>
</div>
<div class="form-row">
    <div class="form-group">
        <label for="mobile">Mobile *</label>
        <input type="tel" id="mobile" name="mobile" class="form-control" value="{{ old('mobile', $vendor->mobile ?? '') }}" maxlength="15" required>
    </div>
    <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" class="form-control" value="{{ old('email', $vendor->email ?? '') }}" maxlength="255">
    </div>
</div>
<div class="form-row">
    <div class="form-group">
        <label for="gst_number">GST Number <span class="text-muted">(optional)</span></label>
        <input id="gst_number" name="gst_number" class="form-control" value="{{ old('gst_number', $vendor->gst_number ?? '') }}" minlength="15" maxlength="15" style="text-transform:uppercase">
        <div class="form-hint">Enter the 15-character GSTIN, if applicable.</div>
    </div>
    <div class="form-group">
        <label for="address">Address</label>
        <textarea id="address" name="address" class="form-control" rows="3" maxlength="1000">{{ old('address', $vendor->address ?? '') }}</textarea>
    </div>
</div>
