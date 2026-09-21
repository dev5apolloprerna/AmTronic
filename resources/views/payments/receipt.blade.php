<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $payment->receipt_number }}</title>
    <style>
        :root { --green:#4f8128; --green-dark:#2f6d16; --green-deep:#285e10; --ink:#141b21; --paper:#fff; }
        * { box-sizing: border-box; }
        html, 
        body { margin:0; padding:0; background:#fff; color:#111; font-family: DejaVu Sans, Arial, Helvetica,, sans-serif; font-size:9.6px; }
        table { width:100%; border-collapse:collapse; border-spacing:0; }
        .receipt-page { width:128mm; min-height:192mm; padding:9mm 9mm 7mm; background:#ffffff; overflow:hidden; }
 .amount-words{text-align:center;margin:8px 0 0}.due{text-align:center;margin-top:8px;font-weight:bold}.
        /* ================= HEADER (compact, stacked for A5 width) ================= */
        .header-table { table-layout:fixed; }
        .header-table td { vertical-align:top; }
        .logo-cell { width:52%; }
        .logo-cell img { display:block; width:38mm; max-height:15mm; object-fit:contain; }
        .contact-cell { width:48%; text-align:right; line-height:1.4; font-size:8.6px; }
        .contact-cell .phone { font-size:10px; font-weight:700; color:#141b21; }
        .address-strip { margin-top:1.5mm; font-size:8.8px; line-height:1.4; color:#333; }
        .tax-strip { margin-top:1mm; font-size:8.8px; }
        .tax-strip strong { color:#2f6d16; }
        .accent-bar { margin-top:2.2mm; height:1.6mm; }
        .accent-green { float:left; width:11mm; height:1.6mm; background:#4f8128; }
        .accent-dark { overflow:hidden; height:1.6mm; background:#141b21; }

        /* ================= TITLE + RECEIPT META ================= */
        .doc-top-layout { table-layout:fixed; margin-top:2.2mm; margin-bottom:2mm; }
        .doc-title-cell { width:42%; vertical-align:top; padding-top:0.5mm; }
        .doc-title { font-size:16px; font-weight:800; color:#0d1720; letter-spacing:.4px; line-height:1.1; text-transform:uppercase; }
        .doc-meta-cell { width:58%; border-left:1px solid #6f756b; padding-left:3.5mm; vertical-align:top; }
        .doc-meta td { height:4.6mm; vertical-align:middle; font-size:9px; white-space:nowrap; }
        .doc-meta .label { width:42%; font-weight:700; }
        .doc-meta .colon { width:5%; text-align:center; }
        .doc-meta .line { width:53%; border-bottom:1px solid #333; padding-left:1.2mm; white-space:normal; }

        /* ================= RECEIVED FROM BOX ================= */
        .party-box { width:100%; border:1px solid #8ea37d; border-radius:2.2mm; border-collapse:separate !important; border-spacing:0 !important; overflow:hidden !important; background:#fff; margin-bottom:2.2mm; }
        .party-box > tbody > tr > td { padding:0 !important; border:0 !important; vertical-align:top; }
        .party-tag-bar { height:6.5mm; line-height:6.5mm; padding:0 3.5mm; color:#fff; background:#285e10; font-size:10px; font-weight:800; letter-spacing:.3px; }
        .party-fields-wrap { padding:1.8mm 3.5mm 1.6mm 3.5mm !important; }
        .party-fields td { height:4.2mm; vertical-align:middle; font-size:9.2px; }
        .party-fields .label { width:32%; white-space:nowrap; }
        .party-fields .colon { width:4%; text-align:center; }
        .party-fields .line { border-bottom:1px dotted #7f857a; padding-left:1.2mm; }

        /* ================= AMOUNT BOX ================= */
        .amount-box { border:1px solid #799169; border-radius:2.2mm; overflow:hidden; margin-bottom:2.2mm; }
        .amount-box .amount-label { background:#f2f6ee; color:#2f6d16; font-weight:700; font-size:9.5px; text-align:center; padding:1.6mm; letter-spacing:.3px; }
        .amount-box .amount-value { background:#2f6d16; color:#fff; font-weight:800; font-size:17px; text-align:center; padding:2.6mm; }

        /* ================= NOTES ================= */
        .notes-block { font-size:9px; color:#333; margin-bottom:2.2mm; line-height:1.5; }
        .notes-block strong { color:#2f6d16; }

        /* ================= SIGNATURE ================= */
        .signature-table { table-layout:fixed; margin-top:2mm; }
        .signature-spacer { width:52%; }
        .signature-cell { width:48%; text-align:center; vertical-align:top; }
        .company-sign { font-size:9.5px; margin-bottom:0.8mm; } .company-sign strong { color:#2f6d16; }
        .signature-image-wrap { width:32mm; height:9mm; margin:0 auto 0.5mm; text-align:center; overflow:hidden; }
        .signature-image { display:block; width:100%; height:100%; object-fit:contain; object-position:center bottom; }
        .sign-line { width:32mm; margin:0 auto 1mm; border-top:1px solid #222; } .authorized { font-size:9.3px; }

        /* ================= FOOTER ================= */
        .footer-note { margin-top:3mm; padding-top:2mm; border-top:1px solid #ccc; text-align:center; font-size:8.3px; font-style:italic; color:#555; }

        @page { size:A5 portrait; margin:0; }
    </style>
</head>

@php
    $designPath = base_path('design');
    $logoPath = $designPath.'/glass-grip-logo.png';
    $signaturePath = $designPath.'/signature.png';
@endphp

<body>
<div class="receipt-page">

    {{-- COMPANY HEADER (compact) --}}
    <table class="header-table"><tr>
        <td class="logo-cell"><img src="{{ $logoPath }}" alt="GlassGrip Masking Tapes Logo"></td>
        <td class="contact-cell">
            <div class="phone">☎ {{ config('invoice.phone') ?: '+91 8866477000' }}</div>
            <div>✉ {{ config('invoice.email') ?: 'ankitgandhi8383@gmail.com' }}</div>
        </td>
    </tr></table>
    <div class="address-strip">{{ config('invoice.address') }}, {{ config('invoice.city') }} - {{ config('invoice.postcode') }}, {{ config('invoice.state') }}, India.</div>
    <div class="tax-strip"><strong>PAN:</strong> {{ config('invoice.pan_number') ?: 'ALTPG0235F' }} &nbsp;&nbsp; <strong>GST No.:</strong> {{ config('invoice.gst_number') ?: '24ALTPG0235F2ZD' }}</div>
    <div class="accent-bar"><div class="accent-green"></div><div class="accent-dark"></div></div>

    {{-- TITLE + RECEIPT META --}}
    <table class="doc-top-layout"><tr>
        <td class="doc-title-cell"><div class="doc-title">Payment<br>Receipt</div></td>
        <td class="doc-meta-cell">
            <table class="doc-meta">
                <tr><td class="label">Receipt No.</td><td class="colon">:</td><td class="line"><strong>{{ $payment->receipt_number }}</strong></td></tr>
                <tr><td class="label">Payment Date</td><td class="colon">:</td><td class="line"><strong>{{ $payment->payment_date->format('d M Y') }}</strong></td></tr>
                <tr><td class="label">Payment Method</td><td class="colon">:</td><td class="line">{{ ucwords(str_replace('_', ' ', $payment->payment_method)) }}</td></tr>
                <tr><td class="label">Reference No.</td><td class="colon">:</td><td class="line">{{ $payment->reference_number ?: '-' }}</td></tr>
                <tr><td class="label">Collected By</td><td class="colon">:</td><td class="line">{{ $payment->enteredBy->name ?? '-' }}</td></tr>
            </table>
        </td>
    </tr></table>

    {{-- RECEIVED FROM --}}
    <table class="party-box"><tr><td>
        <div class="party-tag-bar">RECEIVED FROM</div>
        <div class="party-fields-wrap">
            <table class="party-fields">
                <tr><td class="label">Company Name</td><td class="colon">:</td><td class="line"><strong>{{ $payment->customer->name }}</strong></td></tr>
                @if($payment->customer->contact_person)
                <tr><td class="label">Contact Person</td><td class="colon">:</td><td class="line">{{ $payment->customer->contact_person }}</td></tr>
                @endif
                @if($payment->customer->phone)
                <tr><td class="label">Phone</td><td class="colon">:</td><td class="line">{{ $payment->customer->phone }}</td></tr>
                @endif
            </table>
        </div>
    </td></tr></table>

    {{-- AMOUNT RECEIVED --}}
    <div class="amount-box">
            <p class="amount-words">{{ $amountInWords }}</p>
            <div class="due">Due Amount: &#8377;{{ number_format($dueAmount, 2) }}</div>
        <div class="amount-label">AMOUNT RECEIVED</div>
        <div class="amount-value">₹ {{ number_format($payment->amount, 2) }}</div>
    </div>

    @if($payment->notes)
    <div class="notes-block"><strong>Notes:</strong> {{ $payment->notes }}</div>
    @endif

    {{-- SIGNATURE --}}
    <table class="signature-table"><tr>
        <td class="signature-spacer"></td>
        <td class="signature-cell">
            <div class="company-sign">For <strong>{{ config('invoice.company_name') }}</strong></div>
            <div class="signature-image-wrap"><img src="{{ $signaturePath }}" class="signature-image" alt="Authorised Signature"></div>
            <div class="sign-line"></div>
            <div class="authorized">Authorised Signatory</div>
        </td>
    </tr></table>

    <div class="footer-note">This is a computer-generated company-wise payment receipt and does not require a signature.</div>
</div>
</body>
</html>