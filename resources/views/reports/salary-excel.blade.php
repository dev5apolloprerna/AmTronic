@php($d = fn ($v) => \App\Support\SalaryCalculator::days($v))
<html xmlns:x="urn:schemas-microsoft-com:office:excel">
<head><meta charset="UTF-8"></head>
<body>
    <table border="1">
        <tr><th colspan="15">Salary Report - {{ $periodLabel }}</th></tr>
        <tr><td colspan="15">Days in month: {{ $daysInMonth }} | Paid leave: {{ \App\Support\SalaryCalculator::PAID_LEAVES_PER_MONTH }} days per employee | Leave = Absent + 0.5 per Half Day | Per Day = Monthly Salary / {{ $daysInMonth }}</td></tr>
        <tr>
            <th>Sr.</th>
            <th>Employee</th>
            <th>Designation</th>
            <th>Monthly Salary</th>
            <th>Per Day</th>
            <th>Present</th>
            <th>Half Day</th>
            <th>Absent</th>
            <th>Not Marked</th>
            <th>Leave Days</th>
            <th>Paid Leave</th>
            <th>Unpaid Leave</th>
            <th>Payable Days</th>
            <th>Deduction</th>
            <th>Net Salary</th>
        </tr>
        @foreach($rows as $i => $row)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $row->employee->name }}</td>
                <td>{{ $row->employee->designation?->name ?: '-' }}</td>
                <td>{{ number_format($row->monthly_salary, 2, '.', '') }}</td>
                <td>{{ number_format($row->per_day, 2, '.', '') }}</td>
                <td>{{ $row->present }}</td>
                <td>{{ $row->half_day }}</td>
                <td>{{ $row->absent }}</td>
                <td>{{ $row->not_marked }}</td>
                <td>{{ $d($row->leave_days) }}</td>
                <td>{{ $d($row->paid_leave) }}</td>
                <td>{{ $d($row->unpaid_leave) }}</td>
                <td>{{ $d($row->payable_days) }}</td>
                <td>{{ number_format($row->deduction, 2, '.', '') }}</td>
                <td>{{ number_format($row->net_salary, 2, '.', '') }}</td>
            </tr>
        @endforeach
        <tr>
            <th colspan="3">Total</th>
            <th>{{ number_format($totals->monthly_salary, 2, '.', '') }}</th>
            <th colspan="9"></th>
            <th>{{ number_format($totals->deduction, 2, '.', '') }}</th>
            <th>{{ number_format($totals->net_salary, 2, '.', '') }}</th>
        </tr>
    </table>
</body>
</html>
