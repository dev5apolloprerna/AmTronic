<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #222
        }

        h1 {
            text-align: center
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px
        }

        th,
        td {
            border: 1px solid #777;
            padding: 8px;
            text-align: left
        }

        .right {
            text-align: right
        }

        .meta {
            line-height: 1.7
        }
    </style>
</head>

<body>
    <h1>PURCHASE ORDER</h1>
    <div class="meta"><strong>PO:</strong> {{ $purchaseOrder->po_number }}<br><strong>Date:</strong> {{
        $purchaseOrder->order_date->format('d-m-Y') }}<br><strong>Vendor:</strong> {{ $purchaseOrder->vendor->name
        }}<br>{{ $purchaseOrder->vendor->address }}</div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Product</th>
                <th class="right">Qty</th>
                <th class="right">Rate</th>
                <th class="right">Amount</th>
            </tr>
        </thead>
        <tbody>@foreach($purchaseOrder->items as $item)<tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $item->product->name }}</td>
                <td class="right">{{ $item->quantity }} {{ $item->product->unit }}</td>
                <td class="right">Rs. {{ number_format($item->rate,2) }}</td>
                <td class="right">Rs. {{ number_format($item->amount,2) }}</td>
            </tr>@endforeach<tr>
                <th colspan="4" class="right">Total</th>
                <th class="right">Rs. {{ number_format($purchaseOrder->total_amount,2) }}</th>
            </tr>
        </tbody>
    </table>@if($purchaseOrder->notes)<p><strong>Notes:</strong> {{ $purchaseOrder->notes }}</p>@endif
</body>

</html>