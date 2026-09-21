<html xmlns:x="urn:schemas-microsoft-com:office:excel">
<head><meta charset="UTF-8"></head>
<body>
    <table>
        <tr><th colspan="12">Sales Report</th></tr>
        <tr><td colspan="12">Period: {{ $fromDate ?: 'Beginning' }} to {{ $toDate ?: 'Today' }}{{ $selectedCustomer ? ' | Customer: '.$selectedCustomer->name : '' }}</td></tr>
        <tr><td colspan="12">Total Invoices: {{ $totals['count'] }} | Net Amount: {{ number_format($totals['total_amount'], 2, '.', '') }}</td></tr>
        <tr>
            <th>Invoice No.</th>
            <th>Reference No.</th>
            <th>Date</th>
            <th>Customer</th>
            <th>Created By</th>
            <th>Sub Total</th>
            <th>Discount</th>
            <th>Total Amount</th>
            <th>CGST</th>
            <th>SGST</th>
            <th>IGST</th>
            <th>Net Amount</th>
        </tr>
        @foreach($invoices as $invoice)
            <tr>
                <td>{{ $invoice->invoice_number }}</td>
                <td>{{ $invoice->other_reference ?: '-' }}</td>
                <td>{{ $invoice->invoice_date->format('Y-m-d') }}</td>
                <td>{{ $invoice->customer->name }}</td>
                <td>{{ $invoice->quotation->user->name ?? '-' }}</td>
                <td>{{ number_format($invoice->sub_total, 2, '.', '') }}</td>
                <td>{{ number_format($invoice->discount_amount, 2, '.', '') }}</td>
                <td>{{ number_format($invoice->sub_total - $invoice->discount_amount, 2, '.', '') }}</td>
                <td>{{ number_format($invoice->cgst_amount, 2, '.', '') }}</td>
                <td>{{ number_format($invoice->sgst_amount, 2, '.', '') }}</td>
                <td>{{ number_format($invoice->igst_amount, 2, '.', '') }}</td>
                <td>{{ number_format($invoice->total_amount, 2, '.', '') }}</td>
            </tr>
        @endforeach
    </table>
</body>
</html>