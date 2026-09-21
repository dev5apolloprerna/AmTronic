<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tax Invoice {{ $invoice->invoice_number }}</title>
    <style>
        :root { --green:#4f8128; --green-dark:#2f6d16; --green-deep:#c02026; --ink:#141b21; --paper:#fff; }
        * { box-sizing: border-box; }
        html, body { margin:0; padding:0; background:#fff; color:#111; font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size:10.3px; }
        table { width:100%; border-collapse:collapse; border-spacing:0; }
        .invoice-page { width:182mm; min-height:276mm; padding:13mm 14mm 8mm; background:#ffffff; overflow:hidden; }

        /* ================= HEADER (same as quotation) ================= */
        .header-table { table-layout:fixed; }
        .header-table td { text-align:left; }
        .logo-cell { width:39%; }
        .logo-cell img { display:block; width:60mm; max-height:24mm; object-fit:contain; }
        .address-cell { width:31%; border-left:1px solid #555; border-right:1px solid #555; padding:1.5mm 5mm; line-height:1.4; font-size:10.5px; }
        .contact-cell { width:30%; padding-left:5mm; line-height:1.4; font-size:10.5px; }
        .icon-row td { padding:0; vertical-align:top; }
        .icon { width:28px; color:#4f8128; font-size:18px; font-weight:700; text-align:left; }
        .contact-divider { border-top:1px solid #4f8128; margin:1.5mm 0 1mm; }
        .tax-table td { padding:0; font-size:12.2px; }
        .tax-label { width:34%; font-size:14px !important; font-weight:700; }
        .tax-colon { width:8%; text-align:center; }
        .accent-bar { margin-top:3mm; height:2.1mm; }
        .accent-green { float:left; width:15mm; height:2.1mm; background:#4f8128; }
        .accent-dark { overflow:hidden; height:2.1mm; background:#141b21; }

        /* ================= TITLE + INVOICE META ================= */
        .doc-top-layout { table-layout:fixed; margin-top:2.5mm; margin-bottom:2mm; }
        .doc-title-cell { width:40%; padding-right:4mm; vertical-align:top; padding-top:1mm; }
        .doc-title { font-size:24px; font-weight:800; color:#0d1720; letter-spacing:.6px; line-height:1; text-transform:uppercase; text-shadow:1.2px 1.2px 0 rgba(0,0,0,.16); }
        .doc-meta-cell { width:60%; border-left:1px solid #6f756b; padding-left:4mm; vertical-align:top; }
        .doc-meta { table-layout:fixed; }
        .doc-meta td { height:4.6mm; vertical-align:middle; font-size:10.4px; white-space:nowrap; }
        .doc-meta .label { width:40%; font-weight:700; }
        .doc-meta .colon { width:4%; text-align:center; }
        .doc-meta .line { width:56%; border-bottom:1px solid #333; padding-left:1.5mm; white-space:normal; }

        /* ================= BUYER / DESPATCH BOXES (same pattern as Bill To / Ship To) ================= */
        .party-meta-layout { table-layout:fixed; border-collapse:separate !important; border-spacing:0 !important; margin-bottom:2mm; }
        .party-meta-layout > tbody > tr > td { vertical-align:top; }
        .bill-cell { width:50%; padding-right:2mm !important; border:0 !important; vertical-align:top; }
        .ship-cell { width:50%; padding-left:2mm !important; border:0 !important; vertical-align:top; }
        .party-box { width:100%; border:1px solid #8ea37d; border-radius:2.6mm; border-collapse:separate !important; border-spacing:0 !important; overflow:hidden !important; background:#fff; }
        .party-box > tbody > tr > td { padding:0 !important; border:0 !important; vertical-align:top; }
        .party-tag-bar { vertical-align:middle;height:7.5mm; line-height:7.5mm; padding:0 4.5mm; color:#fff; background:#c02026; font-size:12px; font-weight:800; letter-spacing:.3px; white-space:nowrap; overflow:hidden; }
        .party-fields-wrap { padding:1.8mm 3.6mm 1.6mm 3.6mm !important; }
        .party-fields { table-layout:fixed; border-collapse:collapse; margin:0; }
        .party-fields td { height:4.4mm; vertical-align:middle; font-size:10.6px; }
        .party-fields .label { width:38%; white-space:nowrap; }
        .party-fields .colon { width:6%; text-align:center; }
        .party-fields .line { border-bottom:1px dotted #7f857a; padding-left:1.5mm; }

        /* ================= ITEMS + SUMMARY (single continuous grid, green header) ================= */
        .items-table {  border-collapse:separate; border-spacing:0; border:1px solid #71905e; border-radius:2.5mm; overflow:hidden; margin-bottom:2mm; }
        .items-table th { height:6.5mm; padding:1mm; color:#fff; background:#36751b; border-right:1px solid rgba(255,255,255,.5); font-size:12.5px; line-height:1.25; font-weight:700; text-align:center; vertical-align:middle; }
        .items-table th:last-child, .items-table td:last-child { border-right:0; }
        .items-table td { border-top:1px solid #c7cdc4; border-right:1px solid #c7cdc4; padding:1.1mm 1.6mm; font-size:10.6px; vertical-align:top; }
        .items-table small { display:block; color:#555; font-size:9.6px; margin-top:.6mm; line-height:1.35; }
        .text-center { text-align:center; } .text-right { text-align:right; }
        .items-table tr.summary-row td { background:#f2f6ee; border-top:1px solid #c7cdc4; padding:1mm 1.6mm; font-size:10.2px; font-weight:600; }
        .items-table tr.total-row td { background:#2f6d16; color:#fff; font-weight:800; font-size:11.3px; padding:1.5mm 1.6mm; border-top:1px solid #2f6d16; }

        /* ================= FOOTER BOX: Amount in Words + Declaration + Signature, one combined card ================= */
        .footer-box { table-layout:fixed; border-collapse:collapse; border:1px solid #799169; border-radius:2.4mm; overflow:hidden; margin-top:1mm; }
        .footer-box td { padding:2mm 3.5mm; vertical-align:top; }
        .fb-row1-left { border-bottom:1px solid #cfd8c8; }
        .fb-row1-right { border-bottom:1px solid #cfd8c8; border-left:1px solid #cfd8c8; text-align:right; font-size:10.3px; }
        .fb-row2-left { }
        .fb-row2-right { border-left:1px solid #cfd8c8; text-align:center; vertical-align:middle; }
        .aw-label { color:#2f6d16; font-weight:700; font-size:9.8px; }
        .aw-value { font-size:11.3px; margin-top:0.6mm; }
        .declaration-title-row { margin-bottom:1mm; height:6.5mm; font-size:0; }
        .declaration-icon { display:inline-block; vertical-align:middle; width:6.5mm; height:6.5mm; line-height:6.5mm; border-radius:3.3mm; background:#4f8128; color:#fff; text-align:center; font-weight:700; font-size:10px; }
        .declaration-heading { display:inline-block; vertical-align:middle; padding-left:2mm; font-size:12px; font-weight:800; }
        .declaration-text { font-size:9.8px; line-height:1.45; color:#333; }
        .company-sign { font-size:11px; margin-bottom:1mm; } .company-sign strong { color:#2f6d16; }
        .signature-image-wrap { width:38mm; height:10mm; margin:0 auto 0.5mm; text-align:center; overflow:hidden; }
        .signature-image { display:block; width:100%; height:100%; object-fit:contain; object-position:center bottom; }
        .sign-line { width:38mm; margin:0 auto 1mm; border-top:1px solid #222; } .authorized { font-size:10.3px; }

        /* ================= FOOTER ================= */
        .footer-note { margin-top:2mm; padding-top:1.5mm; border-top:1px solid #ccc; text-align:center; font-size:9.5px; font-style:italic; color:#555; }

        @page { size:A4 portrait; margin:0; }
    </style>
</head>

@php
    $customer = $invoice->customer;
    $designPath = base_path('design');
    $logoPath = $designPath.'/logo.png';
    $signaturePath = $designPath.'/signature.png';
    $shippingAddress = data_get($invoice, 'shipping_address') ?: data_get($invoice, 'quotation.shipping_address') ?: $customer->address;
    $shippingAddressLine2 = data_get($invoice, 'shipping_address_line_2') ?: data_get($invoice, 'quotation.shipping_address_line_2') ?: $customer->address_line_2;
    $shippingCity = data_get($invoice, 'shipping_city') ?: data_get($invoice, 'quotation.shipping_city') ?: $customer->city;
    $shippingState = data_get($invoice, 'shipping_state') ?: data_get($invoice, 'quotation.shipping_state') ?: $customer->state;
    $shippingPincode = data_get($invoice, 'shipping_pincode') ?: data_get($invoice, 'quotation.shipping_pincode') ?: $customer->pincode;
@endphp

<body>
<div class="invoice-page">

    {{-- COMPANY HEADER --}}
    <table class="header-table"><tr>
        <td class="logo-cell"><img src="{{ $logoPath }}" alt="AmTronics Logo"></td>
        <td class="address-cell"><span style="font-size:12px;">{!! config('invoice.address') !!}<br>{{ config('invoice.city') }} - {{ config('invoice.postcode') }}<br>{{ config('invoice.state') }}, India.</span></td>
        <td class="contact-cell">
            <table class="icon-row"><tr><td class="icon">☎</td><td style="vertical-align:middle;font-size:15px;">{{ config('invoice.phone') ?: '+91 98240 22744' }}</td></tr><tr><td class="icon">✉</td><td style="vertical-align:middle;font-size:13px;">{{ config('invoice.email') ?: 'amtronics1959@gmail.com' }}</td></tr></table>
            <div class="contact-divider"></div>
            <table class="tax-table"><tr><td class="tax-label">PAN</td><td class="tax-colon">:</td><td>{{ config('invoice.pan_number') ?: 'ALTPG0235F' }}</td></tr><tr><td class="tax-label">GST No.</td><td class="tax-colon">:</td><td>{{ config('invoice.gst_number') ?: '24ALTPG0235F2ZD' }}</td></tr></table>
        </td>
    </tr></table>
    <div class="accent-bar"><div class="accent-green"></div><div class="accent-dark"></div></div>

    {{-- TITLE + INVOICE META (all fields from the original document-header table) --}}
    <table class="doc-top-layout"><tr>
        <td class="doc-title-cell"><div class="doc-title">Tax Invoice</div></td>
        <td class="doc-meta-cell">
            <table class="doc-meta">
                <tr><td class="label">Invoice No.</td><td class="colon">:</td><td class="line"><strong>{{ $invoice->invoice_number }}</strong></td></tr>
                <tr><td class="label">Dated</td><td class="colon">:</td><td class="line"><strong>{{ $invoice->invoice_date->format('d M Y') }}</strong></td></tr>
                <tr><td class="label">Reference No.</td><td class="colon">:</td><td class="line">{{ $invoice->other_reference ?: $invoice->quotation->quotation_number }}</td></tr>

            </table>
        </td>
    </tr></table>

    {{-- BUYER + DESPATCH DETAILS BOXES (same visual pattern as Bill To / Ship To) --}}
    <table class="party-meta-layout"><tr>
        <td class="bill-cell">
            <table class="party-box"><tr><td>
                <div class="party-tag-bar">BUYER</div>
                <div class="party-fields-wrap">
                    <table class="party-fields">
                        <tr><td class="label">Company Name</td><td class="colon">:</td><td class="line"><strong>{{ $customer->name }}</strong></td></tr>
                        <tr><td class="label">Address Line 1</td><td class="colon">:</td><td class="line">{{ $customer->address ?: '-' }}</td></tr>
                        <tr><td class="label">Address Line 2</td><td class="colon">:</td><td class="line">{{ $customer->address_line_2 ?: '-' }}</td></tr>
                        <tr><td class="label">City</td><td class="colon">:</td><td class="line">{{ $customer->city ?: '-' }}</td></tr>
                        <tr><td class="label">State</td><td class="colon">:</td><td class="line">{{ $customer->state ?: '-' }}</td></tr>
                        <tr><td class="label">Pincode</td><td class="colon">:</td><td class="line">{{ $customer->pincode ?: '-' }}</td></tr>
                        @if($invoice->quotation->gst_applicable)
                        <tr><td class="label">GST No.</td><td class="colon">:</td><td class="line">{{ $customer->gst_number ?: '-' }}</td></tr>
                        @endif
                    </table>
                </div>
            </td></tr></table>
        </td>
        <td class="ship-cell">
            <table class="party-box"><tr><td>
                <div class="party-tag-bar">DESPATCH DETAILS</div>
                <div class="party-fields-wrap">
                    <table class="party-fields">
                        <tr><td class="label">Company Name</td><td class="colon">:</td><td class="line"><strong>{{ $customer->name }}</strong></td></tr>
                        <tr><td class="label">Address Line 1</td><td class="colon">:</td><td class="line">{{ $shippingAddress ?: '-' }}</td></tr>
                        <tr><td class="label">Address Line 2</td><td class="colon">:</td><td class="line">{{ $shippingAddressLine2 ?: '-' }}</td></tr>
                        <tr><td class="label">City</td><td class="colon">:</td><td class="line">{{ $shippingCity ?: '-' }}</td></tr>
                        <tr><td class="label">State</td><td class="colon">:</td><td class="line">{{ $shippingState ?: '-' }}</td></tr>
                        <tr><td class="label">Pincode</td><td class="colon">:</td><td class="line">{{ $shippingPincode ?: '-' }}</td></tr>
                    </table>
                </div>
            </td></tr></table>
        </td>
    </tr></table>

    {{-- ITEMS + SUB TOTAL / TAX / TOTAL — one continuous grid, exactly as the original --}}
    <table class="items-table">
        <colgroup>
            <col style="width:7%"><col style="width:41%"><col style="width:16%"><col style="width:17%"><col style="width:19%">
        </colgroup>
        <thead>
            <tr>
                <th>Sr No.</th>
                <th style="text-align:left;padding-left:2.5mm;">Description of Goods</th>
                <th>HSN</th>
                <th>No. of <br>Rolls</th>
                <th>Rate</th>
                <th>Total Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->quotation->items as $i => $item)
                <tr>
                    <td class="text-center">{{ $i + 1 }}</td>
                    <td>
                        <strong>{{ $item->product->name }} - Size: {{ number_format($item->size_mtr, 2) }} Mtr </strong>
                        @if($item->product->description)<small>{{ $item->product->description }}</small>@endif

                    </td>
                    <td class="text-right">{{ $item->product->hsn_code }}</td>
                    <td class="text-right">{{ $item->no_of_rolls }}</td>
                    <td class="text-right">{{ number_format($item->price_per_mtr, 2) }}</td>
                    <td class="text-right">{{ number_format($item->amount, 2) }}</td>
                </tr>
            @endforeach
                   

            {{-- Sub Total --}}
            <tr class="summary-row">
                <td></td><td></td><td></td><td></td><td></td>
                <td class="text-right">Sub Total : {{ number_format($invoice->sub_total, 2) }}</td>
            </tr>

            @if($invoice->discount_amount > 0)
            <tr class="summary-row">
                <td></td><td>Less: Discount</td><td></td><td></td>
                <td class="text-right">{{ number_format($invoice->discount_amount, 2) }}</td>
            </tr>
            @endif

             @if($invoice->admin_charges > 0)
            <tr>
                <td></td><td>Admin Charges</td><td></td><td></td>
                <td class="text-right">{{ number_format($invoice->admin_charges, 2) }}</td>
            </tr>
            @endif
            @if($invoice->material_handling_charges > 0)
            <tr>
                <td></td><td>Material Handling Charges</td><td></td><td></td>
                <td class="text-right">{{ number_format($invoice->material_handling_charges, 2) }}</td>
            </tr>
            @endif
            

            @if($invoice->cgst_amount > 0)
            <tr class="summary-row">
                <td></td><td></td><td></td><td></td><td></td>
                <td class="text-right">
                    CGST @ 9% {{ number_format($invoice->cgst_amount, 2) }}<br>
                    SGST @ 9% {{ number_format($invoice->sgst_amount, 2) }}<br>
                    <strong>{{ number_format($invoice->cgst_amount + $invoice->sgst_amount, 2) }}</strong>
                </td>
            </tr>
            @elseif($invoice->igst_amount > 0)
            <tr class="summary-row">
                <td></td><td></td><td></td><td></td><td></td>
                <td class="text-right">IGST @ 18% {{ number_format($invoice->igst_amount, 2) }}</td>
            </tr>
            @endif

            @if($invoice->round_off != 0)
            <tr class="summary-row">
                <td></td><td></td><td></td><td></td><td></td>
                <td class="text-right">Round Off {{ $invoice->round_off > 0 ? '+' : '' }} {{ number_format($invoice->round_off, 2) }}</td>
            </tr>
            @endif

            {{-- Total --}}
            <tr class="total-row">
                <td colspan="3" class="text-right">Total</td>
                <td class="text-right">{{ $invoice->quotation->items->sum('no_of_rolls') }}</td>
                <td></td>
                <td class="text-right">Net Amount : {{ number_format($invoice->total_amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- AMOUNT IN WORDS + DECLARATION + SIGNATURE — one combined box, matching the compact reference footer --}}
    <table class="footer-box">
        <colgroup><col style="width:64%"><col style="width:36%"></colgroup>
        <tr>
            <td class="fb-row1-left">
                <div class="aw-label">AMOUNT CHARGEABLE</div>
                <div class="aw-value"><strong>Indian Rupees {{ number_format($invoice->total_amount, 2) }} Only</strong></div>
            </td>
            <td class="fb-row1-right">E. &amp; O.E.</td>
        </tr>
        <tr>
            <td class="fb-row2-left">
                <div class="declaration-title-row">
                    <div class="declaration-icon">i</div>
                    <div class="declaration-heading">Declaration</div>
                </div>
                <div class="declaration-text">We declare that this invoice shows the actual price of the goods described and that all particulars are true and correct.</div>
            </td>
            <td class="fb-row2-right">
                <div class="company-sign">For <strong>{{ config('invoice.company_name') }}</strong></div>
                <div class="signature-image-wrap"><img src="{{ $signaturePath }}" class="signature-image" alt="Authorised Signature"></div>
                <div class="sign-line"></div>
                <div class="authorized">Authorised Signatory</div>
            </td>
        </tr>
    </table>

    <!-- <div class="footer-note">This is a computer-generated Tax Invoice</div> -->
</div>
</body>
</html>