@php($d = fn ($v) => \App\Support\SalaryCalculator::days($v))
<html xmlns:x="urn:schemas-microsoft-com:office:excel">
<head><meta charset="UTF-8"></head>
<body>
    <table border="1">
        <tr><th colspan="16">Salary Sheet - {{ $periodLabel }}</th></tr>
        <tr><td colspan="16">Days in month: {{ $daysInMonth }} | Paid leave: {{ \App\Support\SalaryCalculator::PAID_LEAVES_PER_MONTH }} days | Net = Monthly Salary - Leave Cut - Deduction + Incentive | Status: {{ ucfirst($monthStatus === 'pending' ? 'not submitted' : $monthStatus) }}</td></tr>
        <tr>
            <th>Sr.</th>
            <th>Employee</th>
            <th>Designation</th>
            <th>Monthly Salary</th>
            <th>Full Day</th>
            <th>Half Day</th>
            <th>Absent</th>
            <th>Paid Leave</th>
            <th>Unpaid Leave</th>
            <th>Payable Days</th>
            <th>Leave Cut</th>
            <th>Deduction</th>
            <th>Deduction Reason</th>
            <th>Incentive</th>
            <th>Net Salary</th>
            <th>Status</th>
        </tr>
        @foreach($rows as $i => $row)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $row->employee->name }}</td>
                <td>{{ $row->employee->designation?->name ?: '-' }}</td>
                <td>{{ number_format($row->monthly_salary, 2, '.', '') }}</td>
                <td>{{ $row->present }}</td>
                <td>{{ $row->half_day }}</td>
                <td>{{ $row->absent }}</td>
                <td>{{ $d($row->paid_leave) }}</td>
                <td>{{ $d($row->unpaid_leave) }}</td>
                <td>{{ $d($row->payable_days) }}</td>
                <td>{{ number_format($row->leave_deduction, 2, '.', '') }}</td>
                <td>{{ number_format($row->deduction, 2, '.', '') }}</td>
                <td>{{ $row->deduction_reason ?: '' }}</td>
                <td>{{ number_format($row->incentive, 2, '.', '') }}</td>
                <td>{{ number_format($row->net_salary, 2, '.', '') }}</td>
                <td>{{ $row->slip ? ($row->slip->isProcessed() ? 'Processed' : 'Submitted') : 'Pending' }}</td>
            </tr>
        @endforeach
        <tr>
            <th colspan="3">Total</th>
            <th>{{ number_format($totals->monthly_salary, 2, '.', '') }}</th>
            <th colspan="6"></th>
            <th>{{ number_format($totals->leave_deduction, 2, '.', '') }}</th>
            <th>{{ number_format($totals->deduction, 2, '.', '') }}</th>
            <th></th>
            <th>{{ number_format($totals->incentive, 2, '.', '') }}</th>
            <th>{{ number_format($totals->net_salary, 2, '.', '') }}</th>
            <th></th>
        </tr>
    </table>
</body>
</html>
