<html xmlns:x="urn:schemas-microsoft-com:office:excel">
<head><meta charset="UTF-8"></head>
<body>
    <table>
        <tr><th colspan="6">Customer Ledger - {{ $selectedCustomer->name }}</th></tr>
        <tr><td colspan="6">Period: {{ $fromDate ?: 'Beginning' }} to {{ $toDate ?: 'Today' }}</td></tr>
        <tr><td colspan="6">Opening Balance: {{ number_format($openingBalanceBeforeRange, 2, '.', '') }}</td></tr>
        <tr><th>Date</th><th>Description</th><th>Type</th><th>Amount</th><th>Balance After</th><th>Entered By</th></tr>
        @foreach($ledgers as $entry)
            <tr>
                <td>{{ $entry->transaction_date->format('Y-m-d') }}</td>
                <td>{{ $entry->description }}</td>
                <td>{{ $entry->reference_type }}</td>
                <td>{{ number_format($entry->amount, 2, '.', '') }}</td>
                <td>{{ number_format($entry->balance_after, 2, '.', '') }}</td>
                <td>{{ $entry->enteredBy->name ?? '-' }}</td>
            </tr>
        @endforeach
    </table>
</body>
</html>
