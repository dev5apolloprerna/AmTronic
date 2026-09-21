@php $q = $quotation ?? null; @endphp
<div class="card" style="box-shadow:none; border:1px solid var(--color-border); margin-top:10px;">
 <div class="card-header"><h3>Shipping Address</h3><label><input type="checkbox" name="shipping_address_different" id="shippingDifferent" value="1" {{ old('shipping_address_different', $q->shipping_address_different ?? false) ? 'checked' : '' }}> Different from customer address</label></div>
 <div class="card-body">
  <div class="form-row">
   <div class="form-group"><label>Address *</label><input type="text" class="form-control" name="shipping_address" id="shipping_address" value="{{ old('shipping_address', $q->shipping_address ?? '') }}" required></div>
   <div class="form-group"><label>Address Line 2</label><input type="text" class="form-control" name="shipping_address_line_2" id="shipping_address_line_2" value="{{ old('shipping_address_line_2', $q->shipping_address_line_2 ?? '') }}"></div>
  </div>
  <div class="form-row">
   <div class="form-group"><label>State *</label><select class="form-control" name="shipping_state" id="shipping_state" required><option value="">Select state</option>@foreach(config('states') as $state)<option value="{{ $state }}" {{ old('shipping_state', $q->shipping_state ?? '') === $state ? 'selected' : '' }}>{{ $state }}</option>@endforeach</select></div>
   <div class="form-group"><label>City *</label><input class="form-control" name="shipping_city" id="shipping_city" value="{{ old('shipping_city', $q->shipping_city ?? '') }}" required></div>
   <div class="form-group"><label>Pincode *</label><input class="form-control" name="shipping_pincode" id="shipping_pincode" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" value="{{ old('shipping_pincode', $q->shipping_pincode ?? '') }}" required></div>
  </div>
 </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
 const customer = document.getElementById('customer_id'), different = document.getElementById('shippingDifferent');
 const fill = () => { if (different.checked) return; const o=customer.options[customer.selectedIndex]; ['address','address_line_2','state','city','pincode'].forEach(k => { const el=document.getElementById('shipping_'+k); if(el) el.value=o.dataset[k] || ''; }); };
 customer.addEventListener('change', fill); different.addEventListener('change', fill); if (!different.checked) fill();
});
</script>
