<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $deliveryChallan->challan_number }}</title>
    <style>
        :root { --green:#4f8128; --green-dark:#2f6d16; --green-deep:#c02026; --ink:#141b21; --paper:#fff; }
        * { box-sizing: border-box; }
        html, body { margin:0; padding:0; background:#fff; color:#111; font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size:10.3px; }
        table { width:100%; border-collapse:collapse; border-spacing:0; }
        .invoice-page { width:182mm; min-height:276mm; padding:13mm 14mm 8mm; background:#ffffff; overflow:hidden; }

        /* ================= HEADER (same as invoice/quotation) ================= */
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

        /* ================= TITLE + CHALLAN META ================= */
        .doc-top-layout { table-layout:fixed; margin-top:2.5mm; margin-bottom:2mm; }
        .doc-title-cell { width:44%; padding-right:4mm; vertical-align:top; padding-top:1mm;text-align:center; }
        .doc-title { font-size:24px; font-weight:800; color:#0d1720; letter-spacing:.6px; line-height:1; text-transform:uppercase; text-shadow:1.2px 1.2px 0 rgba(0,0,0,.16); }
        .doc-meta-cell { width:56%; border-left:1px solid #6f756b; padding-left:4mm; vertical-align:top; }
        .doc-meta { table-layout:fixed; }
        .doc-meta td { height:5.5mm; vertical-align:middle; font-size:11px; white-space:nowrap; }
        .doc-meta .label { width:38%; font-weight:700; }
        .doc-meta .colon { width:4%; text-align:center; }
        .doc-meta .line { width:58%; border-bottom:1px solid #333; padding-left:1.5mm; white-space:normal; }

        /* ================= DELIVER TO BOX (same pattern as Buyer/Ship To) ================= */
        .party-box { width:100%; border:1px solid #8ea37d; border-radius:2.6mm; border-collapse:separate !important; border-spacing:0 !important; overflow:hidden !important; background:#fff; margin-bottom:2.5mm; }
        .party-box > tbody > tr > td { padding:0 !important; border:0 !important; vertical-align:top; }
        .party-tag-bar { height:7.5mm; line-height:7.5mm; padding:0 4.5mm; color:#fff; background:#c02026; font-size:12px; font-weight:800; letter-spacing:.3px; white-space:nowrap; overflow:hidden; }
        .party-fields-wrap { padding:2mm 4mm 1.8mm 4mm !important; }
        .party-fields { table-layout:fixed; border-collapse:collapse; margin:0; }
        .party-fields td {
    height: 5.2mm;
    vertical-align: middle;
    font-size: 11px;
    padding: 0.6mm 1mm;   /* add spacing */
}

.party-fields .label {
    width: 34%;            /* increase label width */
    white-space: nowrap;
    font-weight: 600;
}

.party-fields .colon {
    width: 6%;             /* more space for colon */
    text-align: center;
}
        .party-fields .line {
    border-bottom: 1px dotted #7f857a;
    padding-left: 2.5mm;   /* more gap from colon */
}

        /* ================= ITEMS TABLE (green header, rounded) ================= */
        .items-table { width:100%; border-collapse:separate; border-spacing:0; border:1px solid #71905e; border-radius:2.5mm; overflow:hidden; margin-bottom:2.5mm; }
        .items-table th { height:7mm; padding:1mm; color:#fff; background:#36751b; border-right:1px solid rgba(255,255,255,.5); font-size:11.5px; line-height:1.25; font-weight:700; text-align:center; vertical-align:middle; }
        .items-table th:last-child, .items-table td:last-child { border-right:0; }
        .items-table td { border-top:1px solid #c7cdc4; border-right:1px solid #c7cdc4; padding:1.6mm; font-size:10.6px; vertical-align:top; text-align:center; }
        .items-table td.desc-cell { text-align:left; }
        .items-table small { display:block; color:#555; font-size:9.6px; margin-top:.6mm; line-height:1.35; }
        .text-center { text-align:center; } .text-right { text-align:right; }

        /* ================= FOOTER BOX: retention note + signature, one combined card ================= */
        .footer-box { table-layout:fixed; border-collapse:collapse; border:1px solid #799169; border-radius:2.4mm; overflow:hidden; margin-top:2mm; }
        .footer-box td { padding:3mm 4mm; vertical-align:top; }
        .fb-left { width:58%; font-size:10.3px; line-height:1.55; color:#333; }
        .fb-right { width:42%; border-left:1px solid #cfd8c8; text-align:center; vertical-align:middle; }
        .company-sign { font-size:11px; margin-bottom:1mm; } .company-sign strong { color:#2f6d16; }
        .signature-image-wrap { width:38mm; height:11mm; margin:0 auto 0.5mm; text-align:center; overflow:hidden; }
        .signature-image { display:block; width:100%; height:100%; object-fit:contain; object-position:center bottom; }
        .sign-line { width:38mm; margin:0 auto 1mm; border-top:1px solid #222; } .authorized { font-size:10.5px; }

        /* ================= FOOTER ================= */
        .footer-note { margin-top:3mm; padding-top:2mm; border-top:1px solid #ccc; text-align:center; font-size:9.5px; font-style:italic; color:#555; }

        @page { size:A4 portrait; margin:0; }
    </style>
</head>

@php
    $invoice = $deliveryChallan->invoice;
    $customer = $invoice->customer;
    $designPath = base_path('design');
    $logoPath = $designPath.'/logo.png';
    $signaturePath = $designPath.'/signature.png';
    $quotationNumber = data_get($invoice, 'quotation.quotation_number');
    $referenceNumber = data_get($invoice, 'reference_no') ?: data_get($invoice, 'reference_number');
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

    {{-- TITLE + CHALLAN META --}}
    <table class="doc-top-layout"><tr>
        <td class="doc-title-cell"><div class="doc-title">Delivery Challan</div></td>
        
    </tr></table>

    {{-- DELIVER TO --}}
        {{-- DELIVER TO + CHALLAN DETAIL --}}
    <table class="party-meta-layout" style="width:100%; table-layout:fixed; border-collapse:separate; border-spacing:0;">
        <tr>

            <!-- LEFT : DELIVER TO (50%) -->
            <td class="bill-cell" style="width:50%; padding-right:2mm; vertical-align:top;">
                <table class="party-box">
                    <tr>
                        <td>
                            <div class="party-tag-bar">DELIVER TO</div>

                            <div class="party-fields-wrap">
                                <table class="party-fields">
                                    <tr>
                                        <td class="label">Company Name</td>
                                        <td class="colon">:</td>
                                        <td class="line">
                                            <strong>{{ $customer->name }}</strong>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td class="label">Address Line 1</td>
                                        <td class="colon">:</td>
                                        <td class="line">{{ $invoice->shipping_address ?: '-' }}</td>
                                    </tr>

                                    <tr>
                                        <td class="label">Address Line 2</td>
                                        <td class="colon">:</td>
                                        <td class="line">{{ $invoice->shipping_address_line_2 ?: '-' }}</td>
                                    </tr>

                                    <tr>
                                        <td class="label">City</td>
                                        <td class="colon">:</td>
                                        <td class="line">{{ $invoice->shipping_city ?: '-' }}</td>
                                    </tr>

                                    <tr>
                                        <td class="label">State</td>
                                        <td class="colon">:</td>
                                        <td class="line">{{ $invoice->shipping_state ?: '-' }} - {{ $invoice->shipping_pincode ?: '-' }}</td>
                                    </tr>
                                </table>
                            </div>

                        </td>
                    </tr>
                </table>
            </td>

            <!-- RIGHT : CHALLAN DETAIL (50%) -->
            <td class="ship-cell" style="width:50%; padding-left:2mm; vertical-align:top;">

                <table class="party-box">
                    <tr>
                        <td>

                            <div class="party-tag-bar">CHALLAN DETAIL</div>

                            <div class="party-fields-wrap">
                                <table class="party-fields">

                                    <tr>
                                        <td class="label">Challan No.</td>
                                        <td class="colon">:</td>
                                        <td class="line">
                                            <strong>{{ $deliveryChallan->challan_number }}</strong>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td class="label">Date</td>
                                        <td class="colon">:</td>
                                        <td class="line">
                                            <strong>{{ $deliveryChallan->challan_date->format('d M Y') }}</strong>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td class="label">
                                            {{ $quotationNumber ? 'Quotation No.' : ($referenceNumber ? 'Reference No.' : 'Quotation No.') }}
                                        </td>
                                        <td class="colon">:</td>
                                        <td class="line">
                                            {{ $quotationNumber ?: $referenceNumber ?: '-' }}
                                        </td>
                                    </tr>

                                </table>
                            </div>

                        </td>
                    </tr>
                </table>

            </td>

        </tr>
    </table>

    {{-- ITEMS --}}
    <table class="items-table">
        <colgroup>
            <col style="width:7%"><col style="width:53%"><col style="width:14%"><col style="width:14%"><col style="width:12%">
        </colgroup>
        <thead>
            <tr>
                <th>#</th>
                <th style="text-align:left;padding-left:2.5mm;">Product / Description</th>
                <th>HSN</th>
                <th>Qty</th>
                <th>Unit</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->quotation->items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td class="desc-cell">
                        <strong>{{ $item->item_name }}@if($item->legacy_size_label) - {{ $item->legacy_size_label }}@endif</strong>
                        @if($item->line_description)<small>{{ $item->line_description }}</small>@endif
                    </td>
                    <td>{{ $item->item_hsn }}</td>
                    <td>{{ $item->qty_label }}</td>
                    <td>{{ $item->item_unit }}</td>
                </tr>
            @endforeach
            
        </tbody>
    </table>

    {{-- RETENTION NOTE + SIGNATURE --}}
    <table class="footer-box"><tr>
        <td class="fb-left">Please retain this document for your records.<br>This is a computer-generated document.</td>
        <td class="fb-right">
            <div class="signature-image-wrap"><img src="{{ $signaturePath }}" class="signature-image" alt="Authorised Signature"></div>
            <div class="sign-line"></div>
            <div class="authorized"><strong>Authorised Signatory</strong></div>
            <div class="company-sign">For <strong>{{ config('invoice.company_name') }}</strong></div>
        </td>
    </tr></table>

    <div class="footer-note">Thank you for your business</div>
</div>
</body>
</html>
