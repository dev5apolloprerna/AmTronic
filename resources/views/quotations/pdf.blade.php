<!DOCTYPE html>

<html lang="en">
<head>
         <meta charset="UTF-8">
    <title>{{ $quotation->quotation_number }}</title>
    <style>
        :root { --green:#4f8128; --green-dark:#2f6d16; --green-deep:#c02026; --ink:#141b21; --paper:#fff; }
        * { box-sizing: border-box; }
        html, body { margin:0; padding:0; background:#fff; color:#111; font-family: DejaVu Sans, Arial, Helvetica, sans-serif !important; font-size:10.3px; }
        table { width:100%; border-collapse:collapse; border-spacing:0; }
        .quotation-page { width:182mm; min-height:276mm; padding:13mm 14mm 8mm; background:#ffffff; overflow:hidden; }
        .header-table { table-layout:fixed; }
        .header-table td { text-align:left; }
        .logo-cell { width:39%; }
        .logo-cell img { display:block; width:60mm; max-height:24mm; object-fit:contain; }
        .address-cell { width:31%; border-left:1px solid #555; border-right:1px solid #555; padding:1.5mm 5mm; line-height:1.4; font-size:10.5px; }
        .contact-cell { width:30%; padding-left:5mm; line-height:1.4; font-size:10.5px; }
        .icon-row td { padding:0; vertical-align:top; }
        .icon { width:28px; color:#c02026; font-size:18px; font-weight:700; text-align:left; }
        .contact-divider { border-top:1px solid #c02026; margin:1.5mm 0 1mm; }
        .tax-table td { padding:0; font-size:12.2px; }
        .tax-label { width:34%; font-size:14px !important; font-weight:700; }
        .tax-colon { width:8%; text-align:center; }
        .accent-bar { margin-top:3mm; height:2.1mm; }
        .accent-green { float:left; width:15mm; height:2.1mm; background:#c02026; }
        .accent-dark { overflow:hidden; height:2.1mm; background:#141b21; }
        .quotation-top-layout { table-layout:fixed; margin-top:3mm; margin-bottom:2.5mm; }
        .quotation-title-cell { width:50%; padding-right:6mm; vertical-align:middle; }
        .quotation-title { font-size:24px; font-weight:800; color:#0d1720; letter-spacing:.8px; line-height:1; text-transform:uppercase; text-shadow:1.4px 1.4px 0 rgba(0,0,0,.16); }
        .quotation-meta-cell { width:50%; border-left:1px solid #6f756b; padding-left:3mm; vertical-align:top; }
        .quote-meta { table-layout:fixed; margin-top:.5mm; }
        .quote-meta td { height:8mm; vertical-align:middle; font-size:11.5px; }
        .quote-meta .label { width:34%; font-weight:700; }
        .quote-meta .colon { width:7%; text-align:center; }
        .quote-meta .line { border-bottom:1px solid #333; }
        .party-meta-layout { table-layout:fixed; border-collapse:separate !important; border-spacing:0 !important; margin-bottom:2.5mm; }
        .party-meta-layout > tbody > tr > td { vertical-align:top; }
        .bill-cell { width:50%; padding-right:2mm !important; border:0 !important; vertical-align:top; }
        .ship-cell { width:50%; padding-left:2mm !important; border:0 !important; vertical-align:top; }
        .party-box { width:100%; height:45mm; border:1px solid #c02026; border-radius:2.6mm;
        border-collapse:separate !important; border-spacing:0 !important; overflow:hidden !important; background:#fff; }
        .party-box > tbody > tr > td { padding:0 !important; border:0 !important; vertical-align:top; }
        .party-tag-bar { height:9mm;vertical-align:middle; line-height:9mm; padding:0 4.5mm; color:#fff;
        background:#c02026; font-size:12px; font-weight:800; letter-spacing:.3px; white-space:nowrap; overflow:hidden; }
        .party-fields-wrap { padding:2mm 3.6mm 1.8mm 3.6mm !important; }
        .party-fields { table-layout:fixed; border-collapse:collapse; margin:0; }
        .party-fields td { height:4.4mm; vertical-align:middle; font-size:11.8px; }
        .party-fields .label { width:34%; white-space:nowrap; }
        .party-fields .colon { width:6%; text-align:center; }
        .party-fields .line { border-bottom:1px dotted #7f857a; }
        .product-table {width:100%;  border-collapse:separate; border-spacing:0; border:1px solid #c02026; border-radius:2.5mm; overflow:hidden; }
        .product-table th { height:8mm; padding:1mm; color:#fff; background:#c02026;
        border-right:1px solid rgba(255,255,255,.5); font-size:12.8px; line-height:1.25; font-weight:700; text-align:center; vertical-align:middle; }
        .product-table th:last-child, .product-table td:last-child { border-right:0; }
        .product-table td { height:4.8mm; border-top:1px solid #c7cdc4; border-right:1px solid #c7cdc4; padding:.3mm 1.4mm; font-size:12.5px; vertical-align:middle; }
        .center { text-align:center; } .right { text-align:right; }
        .rolls-total-row td { height:4.8mm; border-top:1.4px solid #c02026; padding:.3mm 1.4mm; font-size:12.5px; font-weight:700; background:#eef3ea; }
        .total-rolls-label { color:#c02026; }
        .total-rolls-value { color:#c02026; }
        .bottom-layout { margin-top:1mm; }
        .terms-cell { float:left; width:55%; padding:1.5mm 5mm 0 1mm; }
        .totals-cell { float:left; width:42%; }
        .bottom-clear { clear:both; }
        .terms-title-row { margin-bottom:1mm; height:8mm; font-size:0; }
        .terms-icon { display:inline-block; vertical-align:middle; width:8mm; height:8mm; line-height:8mm;
        border-radius:4mm; background:#4f8128; color:#fff; text-align:center; font-weight:700; font-size:12px; }
        .terms-heading { display:inline-block; vertical-align:middle; padding-left:2mm; font-size:15px; font-weight:800; }
        .terms-list td { height:3.8mm; font-size:11.8px; vertical-align:middle; }
        .term-number { width:7mm; text-align:right; padding-right:2mm; }
        .term-line { display:inline-block; width:45mm; border-bottom:1px solid #777; }
        .totals-table { table-layout:fixed; border-collapse:separate; border-spacing:0; border:1px solid #c02026; border-radius:2.4mm; overflow:hidden; }
        .totals-table td { height:5mm; border-bottom:1px solid #c02026; border-right:1px solid #c02026; padding:0 3mm; font-size:13.2px; vertical-align:middle; }
        .totals-table tr:last-child td { border-bottom:0; }
        .total-label { width:51%; } .rupee { width:11%; text-align:center; border-right:none !important; } .total-value { width:38%; text-align:right; }
        .grand-total .total-label, .grand-label { color:#fff; background:#c02026; font-weight:800; font-size:11px; }
        .grand-total .rupee, .grand-total .total-value { font-weight:800; }
        .signature-block { width:45%; float:right; text-align:center; margin-top:8mm; }
        .signature-clear { clear:both; }
        .company-sign { font-size:13.8px; margin-bottom:0.5mm; } .company-sign strong { color:#c02026; font-size:13.8px; }
        .signature-image-wrap { width:46mm; height:9mm; margin:0 auto 1mm; text-align:center; overflow:hidden; }
        .signature-image { display:block; width:100%; height:100%; object-fit:contain; object-position:center bottom; }
        .sign-line { width:45mm; margin:0 auto 1mm; border-top:1px solid #222; } .authorized { font-size:13.5px; }
        
        @page { size:A4 portrait; margin:0; }
    </style>
</head>

@php
    $customer = $quotation->customer;
    $designPath = base_path('design');
    $logoPath = $designPath.'/logo.png';
    $signaturePath = $designPath.'/signature.png';
    $shipName = $quotation->shipping_address_different ? $customer->name : $customer->name;
    $shipAddress = $quotation->shipping_address_different ? $quotation->shipping_address : $customer->address;
    $shipAddress2 = $quotation->shipping_address_different ? $quotation->shipping_address_line_2 : $customer->address_line_2;
    $shipCity = $quotation->shipping_address_different ? $quotation->shipping_city : $customer->city;
    $shipState = $quotation->shipping_address_different ? $quotation->shipping_state : $customer->state;
    $shipPincode = $quotation->shipping_address_different ? $quotation->shipping_pincode : $customer->pincode;
@endphp

<body>
<div class="quotation-page">
    <table class="header-table"><tr>
        <td class="logo-cell"><img src="{{ $logoPath }}" alt="AmTronics Logo"></td>
        <td class="address-cell"><span style="font-size:12px;">{!! config('invoice.address') !!}<br>{{ config('invoice.city') }} - {{ config('invoice.postcode') }}<br>{{ config('invoice.state') }}, India.</span></td>
        <td class="contact-cell">
            <table class="icon-row"><tr><td class="icon">☎</td><td style="vertical-align:middle;font-size:15px;">{{ config('invoice.phone') ?: '+91 98240 22744' }}</td></tr><tr><td class="icon">✉</td><td style="vertical-align:middle;font-size:13px;">{{ config('invoice.email') ?: 'amtronics1959@gmail.com' }}</td></tr></table>
            <div class="contact-divider"></div>
            <table class="tax-table"><tr><td class="tax-label">PAN</td><td class="tax-colon">:</td><td>{{ config('invoice.pan_number') ?: 'ALTPG0235F' }}</td></tr><tr><td class="tax-label">GST No.</td><td class="tax-colon">:</td><td>{{ config('invoice.gst_number') ?: '24ALTPG0235F2ZD' }}</td></tr></table>
        </td>
    </tr></table>
    <div class="accent-bar">
	    <div class="accent-green"></div>
	    <div class="accent-dark"></div>
    </div>
    <table class="quotation-top-layout">
    <tr>
    	<td class="quotation-title-cell">
    		<div class="quotation-title">QUOTATION</div>
    	</td>
    	<td class="quotation-meta-cell">
    	<table class="quote-meta">
    		<tr>
    			<td class="label">Quotation No.</td>
    			<td class="colon">:</td>
    			<td class="line">{{ $quotation->quotation_number }}</td>
    		</tr>
    		<tr>
    			<td class="label">Date</td>
    			<td class="colon">:</td>
    			<td class="line">{{ $quotation->quotation_date->format('d M Y') }}</td>
    		</tr>
    	</table>
    	</td>
    </tr>
    </table>
    <table class="party-meta-layout"><tr>
        <td class="bill-cell"><table class="party-box"><tr><td><div class="party-tag-bar">BILL TO</div><div class="party-fields-wrap"><table class="party-fields"><tr><td class="label">Company Name</td><td class="colon">:</td><td class="line"><strong>{{ $customer->name }}</strong></td></tr><tr><td class="label">Address Line 1</td><td class="colon">:</td><td class="line">{{ $customer->address ?: '-' }}</td></tr><tr><td class="label">Address Line 2</td><td class="colon">:</td><td class="line">{{ $customer->address_line_2 ?: '-' }}</td></tr><tr><td class="label">City</td><td class="colon">:</td><td class="line">{{ $customer->city ?: '-' }}</td></tr><tr><td class="label">State</td><td class="colon">:</td><td class="line">{{ $customer->state ?: '-' }}</td></tr><tr><td class="label">Pincode</td><td class="colon">:</td><td class="line">{{ $customer->pincode ?: '-' }}</td></tr>@if($quotation->gst_applicable)<tr><td class="label">GST No.</td><td class="colon">:</td><td class="line">{{ $customer->gst_number ?: '-' }}</td></tr>@endif</table></div></td></tr></table></td>
        <td class="ship-cell"><table class="party-box"><tr><td><div class="party-tag-bar">SHIP TO</div><div class="party-fields-wrap"><table class="party-fields"><tr><td class="label">Company Name</td><td class="colon">:</td><td class="line"><strong>{{ $shipName }}</strong></td></tr><tr><td class="label">Address Line 1</td><td class="colon">:</td><td class="line">{{ $shipAddress ?: '-' }}</td></tr><tr><td class="label">Address Line 2</td><td class="colon">:</td><td class="line">{{ $shipAddress2 ?: '-' }}</td></tr><tr><td class="label">City</td><td class="colon">:</td><td class="line">{{ $shipCity ?: '-' }}</td></tr><tr><td class="label">State</td><td class="colon">:</td><td class="line">{{ $shipState ?: '-' }}</td></tr><tr><td class="label">Pincode</td><td class="colon">:</td><td class="line">{{ $shipPincode ?: '-' }}</td></tr></table></div></td></tr></table></td>
    </tr></table>
    <table class="product-table">
            <colgroup>
    <col style="width:13mm">
    <col style="width:65mm">
    <col style="width:25mm">
    <col style="width:30mm">
    <col style="width:25mm">
    <col style="width:30mm">
</colgroup>

            <thead>
                <tr>
                    <th>Sr.</th>
                    <th>Description of Goods</th>
                    <th>HSN Code</th>
                    <th>Quantity</th>
                    <th>Rate </th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($quotation->items as $i => $item)<tr>
                    <td class="center">{{ $i + 1 }}</td>
                    <td>{{ $item->item_name }}@if($item->legacy_size_label) - {{ $item->legacy_size_label }}@endif @if($item->line_description)<br><small>{{ $item->line_description }}</small>@endif</td>
                    <td class="center">{{ $item->item_hsn }}</td>
                    <td class="center">{{ $item->qty_with_unit }}</td>
                    <td class="right">{{ number_format($item->rate, 2) }}</td>
                    <td class="right">{{ number_format($item->amount, 2) }}</td>
                </tr>@endforeach
               
            </tbody>
            {{-- A total only makes sense when every line is counted in the same unit. --}}
            @if($quotation->totalQtyLabel())
            <tfoot>
                <tr class="rolls-total-row">
                    <td colspan="3" class="right total-rolls-label">Total Qty</td>
                    <td class="center total-rolls-value">{{ $quotation->totalQtyLabel() }}</td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
            @endif

        </table><br>
    <div class="bottom-layout">
            <div class="terms-cell">
                <div class="terms-title-row">
                </div>
               
            </div>
            <div class="totals-cell">
                <table class="totals-table">
                    <tr>
                        <td class="total-label">Sub Total</td>
                        <td class="rupee">₹</td>
                        <td class="total-value">{{ number_format($quotation->sub_total, 2) }}</td>
                    </tr>@if($quotation->discount_amount > 0)<tr>
                        <td class="total-label">Discount</td>
                        <td class="rupee">₹</td>
                        <td class="total-value">-{{ number_format($quotation->discount_amount, 2) }}</td>
                    </tr>@endif
                    @if($quotation->admin_charges > 0)<tr>
                        <td class="total-label">Admin Charges</td>
                        <td class="rupee">₹</td>
                        <td class="total-value">{{ number_format($quotation->admin_charges, 2) }}</td>
                    </tr>@endif
                    @if($quotation->material_handling_charges > 0)<tr>
                        <td class="total-label">Material Handling Charges</td>
                        <td class="rupee">₹</td>
                        <td class="total-value">{{ number_format($quotation->material_handling_charges, 2) }}</td>
                    </tr>@endif<tr>
                        <td class="total-label">CGST</td>
                        <td class="rupee">₹</td>
                        <td class="total-value">{{ number_format($quotation->cgst_amount, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="total-label">SGST</td>
                        <td class="rupee">₹</td>
                        <td class="total-value">{{ number_format($quotation->sgst_amount, 2) }}</td>
                    </tr>@if($quotation->igst_amount > 0)<tr>
                        <td class="total-label">IGST</td>
                        <td class="rupee">₹</td>
                        <td class="total-value">{{ number_format($quotation->igst_amount, 2) }}</td>
                    </tr>@endif<tr>
                        <td class="total-label">Round Off</td>
                        <td class="rupee">₹</td>
                        <td class="total-value">{{ number_format($quotation->round_off, 2) }}</td>
                    </tr>
                    <tr class="grand-total">
                        <td class="total-label grand-label">Grand Total</td>
                        <td class="rupee">₹</td>
                        <td class="total-value">{{ number_format($quotation->total_amount, 2) }}</td>
                    </tr>
                </table>
            </div>
            <div class="bottom-clear"></div>
        </div>
    <div class="signature-block">
    	<div class="company-sign">For <strong>Amtronics</strong></div>
    	<div class="signature-image-wrap">
    		<img src="{{ $signaturePath }}" class="signature-image" alt="Authorised Signature"></div>
    		<div class="sign-line"></div>
    		<div class="authorized">Authorised Signatory</div>
    	</div>
    	<div class="signature-clear"></div>
</div>
</body>

</html>
