@php($d = fn ($v) => \App\Support\SalaryCalculator::days((float) $v))
@php($totalEarnings = (float) $slip->monthly_salary + (float) $slip->incentive)
@php($totalDeductions = (float) $slip->leave_deduction + (float) $slip->deduction)
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Salary Slip - {{ $slip->employee->name }} - {{ $slip->period_label }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1a1a; margin: 0; background: {{ $pdf ? '#fff' : '#f4f4f4' }}; }
        .page { max-width: 760px; margin: {{ $pdf ? '0' : '24px auto' }}; background: #fff; padding: {{ $pdf ? '0' : '28px' }}; {{ $pdf ? '' : 'box-shadow: 0 2px 10px rgba(0,0,0,.08);' }} }
        .toolbar { max-width: 760px; margin: 18px auto 0; text-align: right; }
        .toolbar a, .toolbar button { display: inline-block; padding: 8px 14px; border-radius: 6px; border: 0; font-size: 13px; text-decoration: none; cursor: pointer; margin-left: 6px; font-family: inherit; }
        .btn-print { background: #111; color: #fff; }
        .btn-pdf { background: #c02026; color: #fff; }
        .btn-back { background: #e5e5e5; color: #111; }
        table { width: 100%; border-collapse: collapse; }
        .head td { vertical-align: middle; padding: 0 0 12px; }
        .head img { width: 230px; }
        .head .addr { text-align: right; font-size: 11px; color: #444; line-height: 1.6; }
        .head .addr strong { color: #111; font-size: 13px; }
        .rule { height: 4px; background: #c02026; border-bottom: 2px solid #111; }
        .title { text-align: center; margin: 14px 0; font-size: 14px; font-weight: bold; letter-spacing: 2px; color: #111; }
        .title span { color: #c02026; }
        .info td { padding: 6px 8px; border: 1px solid #d4d4d4; }
        .info td.k { background: #f6f6f6; width: 22%; font-weight: bold; border-left: 3px solid #c02026; }
        .att { margin-top: 12px; }
        .att th, .att td { border: 1px solid #d4d4d4; padding: 7px 6px; text-align: center; }
        .att th { background: #111; color: #fff; font-size: 11px; border-color: #111; }
        .present { color: #15803d; font-weight: bold; }
        .half { color: #b45309; font-weight: bold; }
        .absent { color: #c02026; font-weight: bold; }
        .money { margin-top: 12px; }
        .money th { background: #111; color: #fff; padding: 7px 8px; text-align: left; border: 1px solid #111; }
        .money th.ded { background: #c02026; border-color: #c02026; }
        .money td { border: 1px solid #d4d4d4; padding: 7px 8px; vertical-align: top; }
        .r { text-align: right !important; }
        .tot td { font-weight: bold; background: #f6f6f6; }
        .net { margin-top: 14px; border: 2px solid #c02026; border-left-width: 8px; padding: 10px 14px; }
        .net .amt { font-size: 18px; font-weight: bold; color: #c02026; }
        .small { font-size: 11px; color: #555; }
        .sign { margin-top: 50px; }
        .sign td { width: 50%; padding-top: 30px; }
        .sign .line { border-top: 1px solid #111; display: inline-block; padding-top: 4px; min-width: 170px; }
        .foot { margin-top: 22px; border-top: 1px solid #e5e5e5; padding-top: 8px; }
        .draft-mark { position: {{ $pdf ? 'fixed' : 'absolute' }}; top: 330px; left: 0; right: 0; text-align: center; font-size: 110px; font-weight: bold; color: rgba(192, 32, 38, 0.10); transform: rotate(-25deg); letter-spacing: 12px; }
        .draft-note { background: #fff7ed; border: 1px solid #fdba74; color: #9a3412; padding: 6px 10px; font-size: 11px; margin-bottom: 10px; }
        .page { position: relative; }
        @media print { .toolbar { display: none; } body { background: #fff; } .page { margin: 0; padding: 0; box-shadow: none; } }
    </style>
</head>
<body>
@unless($pdf)
    <div class="toolbar">
        @if(! empty($backUrl))<a class="btn-back" href="{{ $backUrl }}">&larr; Back</a>@endif
        <button class="btn-print" onclick="window.print()">Print</button>
        @if(! empty($downloadUrl))<a class="btn-pdf" href="{{ $downloadUrl }}">Download PDF</a>@endif
    </div>
@endunless
<div class="page">
    @unless($slip->isProcessed())
        <div class="draft-mark">DRAFT</div>
    @endunless
    <table class="head">
        <tr>
            <td style="width:50%">
                @if(! empty($logoSrc))
                    <img src="{{ $logoSrc }}" alt="{{ $company['company_name'] }}">
                @else
                    <strong style="font-size:20px;color:#c02026;">{{ $company['company_name'] }}</strong>
                @endif
            </td>
            <td class="addr">
                <strong>{{ $company['company_name'] }}</strong><br>
                {{ $company['address'] }}<br>
                {{ $company['city'] }}, {{ $company['state'] }} - {{ $company['postcode'] }}
                @if($company['phone'])<br>Ph: {{ $company['phone'] }}@endif
                @if($company['email'])<br>{{ $company['email'] }}@endif
            </td>
        </tr>
    </table>
    <div class="rule"></div>
    @unless($slip->isProcessed())
        <div class="draft-note" style="margin-top:10px;">Draft slip &mdash; salary for this month is submitted but not processed yet. Amounts may still change.</div>
    @endunless
    <div class="title">SALARY SLIP &mdash; <span>{{ strtoupper($slip->period_label) }}</span></div>

    <table class="info">
        <tr><td class="k">Employee Name</td><td>{{ $slip->employee->name }}</td><td class="k">Slip No.</td><td>{{ $slip->slip_number }}</td></tr>
        <tr><td class="k">Designation</td><td>{{ $slip->employee->designation?->name ?: '-' }}</td><td class="k">Pay Period</td><td>{{ $slip->period_label }}</td></tr>
        <tr><td class="k">Monthly Salary</td><td>₹{{ number_format($slip->monthly_salary, 2) }}</td><td class="k">Per Day Rate</td><td>₹{{ number_format($slip->per_day, 2) }}</td></tr>
    </table>

    <table class="att">
        <tr><th>Days in Month</th><th>Full Day</th><th>Half Day</th><th>Absent</th><th>Paid Leave</th><th>Unpaid Leave</th><th>Payable Days</th></tr>
        <tr>
            <td>{{ $slip->days_in_month }}</td>
            <td class="present">{{ $slip->full_days }}</td>
            <td class="half">{{ $slip->half_days }}</td>
            <td class="absent">{{ $slip->absent_days }}</td>
            <td>{{ $d($slip->paid_leave) }}</td>
            <td>{{ $d($slip->unpaid_leave) }}</td>
            <td>{{ $d($slip->payable_days) }}</td>
        </tr>
    </table>

    <table class="money">
        <tr><th style="width:32%">Earnings</th><th class="r" style="width:18%">Amount (₹)</th><th class="ded" style="width:32%">Deductions</th><th class="ded r" style="width:18%">Amount (₹)</th></tr>
        <tr>
            <td>Basic / Monthly Salary</td><td class="r">{{ number_format($slip->monthly_salary, 2) }}</td>
            <td>Leave Deduction ({{ $d($slip->unpaid_leave) }} unpaid day(s))</td><td class="r">{{ number_format($slip->leave_deduction, 2) }}</td>
        </tr>
        <tr>
            <td>Incentive</td><td class="r">{{ number_format($slip->incentive, 2) }}</td>
            <td>Other Deduction @if($slip->deduction_reason)<br><span class="small">Reason: {{ $slip->deduction_reason }}</span>@endif</td><td class="r">{{ number_format($slip->deduction, 2) }}</td>
        </tr>
        <tr class="tot">
            <td>Total Earnings</td><td class="r">{{ number_format($totalEarnings, 2) }}</td>
            <td>Total Deductions</td><td class="r">{{ number_format($totalDeductions, 2) }}</td>
        </tr>
    </table>

    <div class="net">
        Net Salary Payable: <span class="amt">₹{{ number_format($slip->net_salary, 2) }}</span><br>
        <span class="small">{{ $amountInWords }}</span>
    </div>

    <table class="sign">
        <tr><td><span class="line">Employee Signature</span></td><td class="r">For <strong>{{ $company['company_name'] }}</strong><br><br><span class="line">Authorised Signatory</span></td></tr>
    </table>
    <p class="small foot">
        @if($slip->isProcessed())
            Processed on {{ $slip->processed_at?->format('d M Y') }}{{ $slip->processedBy ? ' by '.$slip->processedBy->name : '' }}.
        @else
            Draft saved on {{ $slip->updated_at->format('d M Y') }}{{ $slip->submittedBy ? ' by '.$slip->submittedBy->name : '' }}.
        @endif
        {{ \App\Support\SalaryCalculator::PAID_LEAVES_PER_MONTH }} paid leaves per month; half day counts as &frac12; leave.
    </p>
</div>
</body>
</html>
