@extends('layouts.app')

@section('title', 'New Quotation')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>New Quotation</h3>
            <a href="{{ route('quotations.index') }}" class="btn btn-secondary btn-sm">&larr; Back</a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('quotations.store') }}">
                @csrf

                <div class="form-row">
                    <div class="form-group">
                        <label for="customer_id">Customer *</label>
                        <select class="form-control" id="customer_id" name="customer_id" required>
                            <option value="">Select customer</option>
                            @foreach($customers as $customer)
                                <option data-address="{{ $customer->address }}" data-address_line_2="{{ $customer->address_line_2 }}" data-state="{{ $customer->state }}" data-city="{{ $customer->city }}" data-pincode="{{ $customer->pincode }}" value="{{ $customer->id }}" {{ old('customer_id') == $customer->id ? 'selected' : '' }}>{{ $customer->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="quotation_date">Quotation Date *</label>
                        <input type="date" class="form-control" id="quotation_date" name="quotation_date" value="{{ old('quotation_date', now()->toDateString()) }}" required>
                    </div>
                </div>
                @include('quotations._shipping', ['quotation' => $quotation ?? null])
                <div class="card" style="box-shadow:none; border:1px solid var(--color-border); margin-top:10px;">
                    <div class="card-header">
                        <h3>Products</h3>
                        <button type="button" id="addItemBtn" class="btn btn-secondary btn-sm">+ Add Product Line</button>
                    </div>
                    <div class="card-body">
                        <div id="itemsContainer" data-next-index="0"></div>

                        <div class="totals-box">
                            <div class="row"><span>Sub Total</span><span>&#8377;<span id="summarySubTotal">0.00</span></span></div>
                            <div class="row">
                                <span>Discount (&#8377;)</span>
                                <span>
                                    <input type="number" step="0.01" min="0" id="discount_amount" name="discount_amount" class="form-control" style="width:130px; text-align:right; display:inline-block; padding:5px 10px;" value="{{ old('discount_amount', 0) }}" placeholder="0">
                                </span>
                            </div>
                            <div id="discountErrorHint" class="form-error" style="display:none; text-align:right; margin-top:-4px; margin-bottom:6px;">Discount cannot be greater than the Sub Total.</div>
                            <div class="row">
                                <span>Admin Charges (&#8377;)</span>
                                <span><input type="number" step="0.01" min="0" id="admin_charges" name="admin_charges" class="form-control" style="width:130px; text-align:right; display:inline-block; padding:5px 10px;" value="{{ old('admin_charges', 0) }}" placeholder="0"></span>
                            </div>
                            <div class="row">
                                <span>Material Handling Charges (&#8377;)</span>
                                <span><input type="number" step="0.01" min="0" id="material_handling_charges" name="material_handling_charges" class="form-control" style="width:130px; text-align:right; display:inline-block; padding:5px 10px;" value="{{ old('material_handling_charges', 0) }}" placeholder="0"></span>
                            </div>
                            <div class="row" id="summaryNetAmountRow" style="display:none;"><span>Total Amount</span><span>&#8377;<span id="summaryNetAmount">0.00</span></span></div>
                            <div class="row">
                                <span>
                                    GST (18%)
                                    <label style="font-weight:400; margin-left:14px;">
                                        <input type="checkbox" id="gst_yes" {{ old('gst_applicable') ? 'checked' : '' }}> Yes
                                    </label>
                                    <label style="font-weight:400; margin-left:10px;">
                                        <input type="checkbox" id="gst_no" {{ old('gst_applicable') ? '' : 'checked' }}> No
                                    </label>
                                    <input type="hidden" name="gst_applicable" id="gst_applicable_hidden" value="{{ old('gst_applicable') ? '1' : '0' }}">
                                </span>
                                <span id="summaryGstRow" style="display:none;">&#8377;<span id="summaryGstAmount">0.00</span></span>
                            </div>
                            <div class="row"><span>Round Off</span><span id="summaryRoundOff">&#8377;0.00</span></div>
                            <div class="row grand"><span>Net Amount</span><span>&#8377;<span id="summaryTotal">0.00</span></span></div>
                        </div>
                    </div>
                </div>

                <div style="margin-top:20px;">
                    <button type="submit" class="btn btn-primary">Create Quotation</button>
                    <span class="form-hint">You can edit this quotation and add/remove products until it's approved.</span>
                </div>
            </form>
        </div>
    </div>

    <template id="itemRowTemplate">
        @include('quotations._item_row', ['index' => '__INDEX__', 'products' => $products, 'item' => null])
    </template>
@endsection
