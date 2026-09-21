<html xmlns:x="urn:schemas-microsoft-com:office:excel">
<head><meta charset="UTF-8"></head>
<body>
    <table>
        <tr><th colspan="2">Pending Payment Report</th></tr>
        <tr><td colspan="2">Generated: {{ now()->format('d M Y') }}</td></tr>
        <tr>
            <th>Customer Name</th>
            <th>Pending Amount</th>
        </tr>
        @foreach($customers as $customer)
            <tr>
                <td>{{ $customer->name }}</td>
                <td>{{ number_format($customer->pending_amount, 2, '.', '') }}</td>
            </tr>
        @endforeach
        <tr>
            <th>Total Pending Amount</th>
            <th>{{ number_format($totalPending, 2, '.', '') }}</th>
        </tr>
    </table>
</body>
</html>
