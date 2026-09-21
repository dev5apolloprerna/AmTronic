<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Ledger - {{ $selectedCustomer->name }}</title>
    <style>
        :root { --green:#4f8128; --green-dark:#2f6d16; --green-deep:#c02026; --ink:#141b21; --paper:#fff; }
        * { box-sizing: border-box; }
        html, body { margin:0; padding:0; background:#fff; color:#111; font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size:10.3px; }
        table { width:100%; border-collapse:collapse; border-spacing:0; }
        .invoice-page { width:269mm; min-height:180mm; padding:10mm 12mm 8mm; background:#ffffff; overflow:hidden; }

        /* ================= HEADER (same as invoice/quotation/challan) ================= */
        .header-table { table-layout:fixed; }
        .header-table td { text-align:left; }
        .logo-cell { width:34%; }
        .logo-cell img { display:block; width:60mm; max-height:24mm; object-fit:contain; }
        .address-cell { width:33%; border-left:1px solid #555; border-right:1px solid #555; padding:1.5mm 5mm; line-height:1.4; font-size:10.5px; }
        .contact-cell { width:33%; padding-left:5mm; line-height:1.4; font-size:10.5px; }
        .icon-row td { padding:0; vertical-align:top; }
        .icon { width:28px; color:#4f8128; font-size:18px; font-weight:700; text-align:left; }
        .contact-divider { border-top:1px solid #4f8128; margin:1.5mm 0 1mm; }
        .tax-table td { padding:0; font-size:12.2px; }
        .tax-label { width:26%; font-size:14px !important; font-weight:700; }
        .tax-colon { width:6%; text-align:center; }
        .accent-bar { margin-top:3mm; height:2.1mm; }
        .accent-green { float:left; width:15mm; height:2.1mm; background:#4f8128; }
        .accent-dark { overflow:hidden; height:2.1mm; background:#141b21; }

        /* ================= TITLE + LEDGER META ================= */
        .doc-top-layout { table-layout:fixed; margin-top:2.5mm; margin-bottom:2mm; }
        .doc-title-cell { vertical-align:top; padding-top:1mm; text-align:center; }
        .doc-title { font-size:22px; font-weight:800; color:#0d1720; letter-spacing:.6px; line-height:1; text-transform:uppercase; text-shadow:1.2px 1.2px 0 rgba(0,0,0,.16); }
        .doc-subtitle { font-size:10.5px; color:#555; margin-top:1mm; }

        /* ================= INFO BOXES (same pattern as Buyer/Ship To/Deliver To) ================= */
        .party-box { width:100%; border:1px solid #8ea37d; border-radius:2.6mm; border-collapse:separate !important; border-spacing:0 !important; overflow:hidden !important; background:#fff; margin-bottom:2.5mm; }
        .party-box > tbody > tr > td { padding:0 !important; border:0 !important; vertical-align:top; }
        .party-tag-bar { height:6.8mm; line-height:6.8mm; padding:0 4.5mm; color:#fff; background:#c02026; font-size:12px; font-weight:800; letter-spacing:.3px; white-space:nowrap; overflow:hidden; }
        .party-fields-wrap { padding:1.8mm 4mm 1.5mm 4mm !important; }
        .party-fields { table-layout:fixed; border-collapse:collapse; margin:0; }
        .party-fields td { height:4.8mm; vertical-align:middle; font-size:11px; padding:0.5mm 1mm; }
        .party-fields .label { width:30%; white-space:nowrap; font-weight:600; }
        .party-fields .colon { width:5%; text-align:center; }
        .party-fields .line { border-bottom:1px dotted #7f857a; padding-left:2.5mm; }

        /* ================= LEDGER TABLE (green header, rounded) ================= */
        .items-table { width:100%; border-collapse:separate; border-spacing:0; border:1px solid #71905e; border-radius:2.5mm; overflow:hidden; margin-bottom:2.5mm; }
        .items-table th { height:7mm; padding:1mm; color:#fff; background:#36751b; border-right:1px solid rgba(255,255,255,.5); font-size:11.5px; line-height:1.25; font-weight:700; text-align:center; vertical-align:middle; }
        .items-table th:last-child, .items-table td:last-child { border-right:0; }
        .items-table td { border-top:1px solid #c7cdc4; border-right:1px solid #c7cdc4; padding:1.6mm; font-size:10.6px; vertical-align:top; text-align:center; }
        .items-table td.desc-cell { text-align:left; }
        .items-table small { display:block; color:#555; font-size:9.6px; margin-top:.6mm; line-height:1.35; }
        .text-center { text-align:center; } .text-right { text-align:right; }
        .row-opening td { background:#f3f6f0; font-weight:700; }
        .amt-debit { color:#8a2323; }
        .amt-credit { color:#2f6d16; }

        /* ================= TOTALS ROW ================= */
        .items-table tfoot td { border-top:1.4px solid #36751b; background:#eaf2e5; font-weight:800; font-size:11px; padding:2mm 1.6mm; }
        .items-table tfoot .totals-label { text-align:right; padding-right:3mm; }
        .items-table tfoot .closing-row td { background:#c02026; color:#fff; font-size:11.5px; }

        /* ================= FOOTER BOX: note + signature ================= */
        .footer-box { table-layout:fixed; border-collapse:collapse; border:1px solid #799169; border-radius:2.4mm; overflow:hidden; margin-top:2mm; }
        .footer-box td { padding:3mm 4mm; vertical-align:top; }
        .fb-left { width:60%; font-size:10.3px; line-height:1.55; color:#333; }
        .fb-right { width:40%; border-left:1px solid #cfd8c8; text-align:center; vertical-align:middle; }
        .company-sign { font-size:11px; margin-bottom:1mm; } .company-sign strong { color:#2f6d16; }
        .signature-image-wrap { width:38mm; height:11mm; margin:0 auto 0.5mm; text-align:center; overflow:hidden; }
        .signature-image { display:block; width:100%; height:100%; object-fit:contain; object-position:center bottom; }
        .sign-line { width:38mm; margin:0 auto 1mm; border-top:1px solid #222; } .authorized { font-size:10.5px; }

        /* ================= FOOTER ================= */
        .footer-note { margin-top:3mm; padding-top:2mm; border-top:1px solid #ccc; text-align:center; font-size:9.5px; font-style:italic; color:#555; }

        @page { size:A4 landscape; margin:0; }
    </style>
</head>

@php
    $designPath = base_path('design');
    $logoPath = $designPath.'/logo.png';
    $signaturePath = $designPath.'/signature.png';

    $runningBalance = $openingBalanceBeforeRange;
    $totalDebit = 0;
    $totalCredit = 0;
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

    {{-- TITLE --}}
    <table class="doc-top-layout"><tr>
        <td class="doc-title-cell" style="width:100%;">
            <div class="doc-title">Customer Ledger</div>
            <div class="doc-subtitle">For the period {{ $fromDate ? \Carbon\Carbon::parse($fromDate)->format('d M Y') : 'Beginning' }} to {{ $toDate ? \Carbon\Carbon::parse($toDate)->format('d M Y') : 'Today' }}</div>
        </td>
    </tr></table>

    {{-- ACCOUNT DETAILS + LEDGER DETAIL --}}
    <table class="party-meta-layout" style="width:100%; table-layout:fixed; border-collapse:separate; border-spacing:0;">
        <tr>
            <!-- LEFT : ACCOUNT DETAILS (50%) -->
            <td style="width:50%; padding-right:2mm; vertical-align:top;">
                <table class="party-box">
                    <tr>
                        <td>
                            <div class="party-tag-bar">ACCOUNT DETAILS</div>
                            <div class="party-fields-wrap">
                                <table class="party-fields">
                                    <tr>
                                        <td class="label">Company Name</td>
                                        <td class="colon">:</td>
                                        <td class="line"><strong>{{ $selectedCustomer->name }}</strong></td>
                                    </tr>
                                    <tr>
                                        <td class="label">Address</td>
                                        <td class="colon">:</td>
                                        <td class="line">{{ $selectedCustomer->address ?: '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="label">GSTIN</td>
                                        <td class="colon">:</td>
                                        <td class="line">{{ $selectedCustomer->gst_number ?: '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="label">Mobile</td>
                                        <td class="colon">:</td>
                                        <td class="line">{{ $selectedCustomer->phone ?: '-' }}</td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>

            <!-- RIGHT : LEDGER DETAIL (50%) -->
            <td style="width:50%; padding-left:2mm; vertical-align:top;">
                <table class="party-box">
                    <tr>
                        <td>
                            <div class="party-tag-bar">LEDGER DETAIL</div>
                            <div class="party-fields-wrap">
                                <table class="party-fields">
                                    <tr>
                                        <td class="label">Period From</td>
                                        <td class="colon">:</td>
                                        <td class="line"><strong>{{ $fromDate ? \Carbon\Carbon::parse($fromDate)->format('d M Y') : 'Beginning' }}</strong></td>
                                    </tr>
                                    <tr>
                                        <td class="label">Period To</td>
                                        <td class="colon">:</td>
                                        <td class="line"><strong>{{ $toDate ? \Carbon\Carbon::parse($toDate)->format('d M Y') : 'Today' }}</strong></td>
                                    </tr>
                                    <tr>
                                        <td class="label">Opening Balance</td>
                                        <td class="colon">:</td>
                                        <td class="line">₹ {{ number_format($openingBalanceBeforeRange, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="label">Generated On</td>
                                        <td class="colon">:</td>
                                        <td class="line">{{ now()->format('d M Y') }}</td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- LEDGER TABLE --}}
    <table class="items-table">
        <colgroup>
            <col style="width:9%"><col style="width:27%"><col style="width:12%"><col style="width:11%"><col style="width:11%"><col style="width:12%"><col style="width:18%">
        </colgroup>
        <thead>
            <tr>
                <th>Date</th>
                <th style="text-align:left;padding-left:2.5mm;">Description</th>
                <th>Type</th>
                <th>Debit (₹)</th>
                <th>Credit (₹)</th>
                <th>Balance (₹)</th>
                <th>Entered By</th>
            </tr>
        </thead>
        <tbody>
            <tr class="row-opening">
                <td colspan="5" style="text-align:left;padding-left:2.5mm;">Opening Balance</td>
                <td>{{ number_format($openingBalanceBeforeRange, 2) }}</td>
                <td>&nbsp;</td>
            </tr>
            @forelse($ledgers as $entry)
                @php
                    $debit = $entry->amount > 0 ? $entry->amount : 0;
                    $credit = $entry->amount < 0 ? abs($entry->amount) : 0;
                    $totalDebit += $debit;
                    $totalCredit += $credit;
                    $runningBalance = $entry->balance_after ?? ($runningBalance + $entry->amount);
                @endphp
                <tr>
                    <td>{{ $entry->transaction_date->format('d M Y') }}</td>
                    <td class="desc-cell">{{ $entry->description }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $entry->reference_type)) }}</td>
                    <td class="amt-debit">{{ $debit ? number_format($debit, 2) : '-' }}</td>
                    <td class="amt-credit">{{ $credit ? number_format($credit, 2) : '-' }}</td>
                    <td>{{ number_format($runningBalance, 2) }}</td>
                    <td>{{ $entry->enteredBy->name ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align:center;color:#777;">No transactions in this period.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" class="totals-label">Total</td>
                <td class="amt-debit">{{ number_format($totalDebit, 2) }}</td>
                <td class="amt-credit">{{ number_format($totalCredit, 2) }}</td>
                <td colspan="2">&nbsp;</td>
            </tr>
            <tr class="closing-row">
                <td colspan="5" class="totals-label">Closing Balance</td>
                <td colspan="2"> {{ number_format($runningBalance, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    {{-- NOTE + SIGNATURE --}}
    <!-- <table class="footer-box"><tr>
        <td class="fb-left">Please retain this document for your records.<br>This is a computer-generated statement. Kindly verify all entries and report any discrepancy within 7 days of receipt.</td>
        <td class="fb-right">
            <div class="signature-image-wrap"><img src="{{ $signaturePath }}" class="signature-image" alt="Authorised Signature"></div>
            <div class="sign-line"></div>
            <div class="authorized"><strong>Authorised Signatory</strong></div>
            <div class="company-sign">For <strong>{{ config('invoice.company_name') }}</strong></div>
        </td>
    </tr></table> -->

    <div class="footer-note">Thank you for your business</div>
</div>
</body>
</html>