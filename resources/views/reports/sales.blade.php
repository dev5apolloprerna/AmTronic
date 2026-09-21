@extends('layouts.app')

@section('title', 'Sales Report')

@section('content')
    <div class="card">
        <div class="card-header"><h3>Sales Report</h3></div>
        <div class="card-body">
            <form method="GET" class="filters-bar">
                <div class="form-group">
                    <label>From Date</label>
                    <input type="date" name="from_date" class="form-control" value="{{ $fromDate }}">
                </div>
                <div class="form-group">
                    <label>To Date</label>
                    <input type="date" name="to_date" class="form-control" value="{{ $toDate }}">
                </div>
                <div class="form-group">
                    <label>Customer</label>
                    <select name="customer_id" class="form-control">
                        <option value="">All Customers</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" {{ (string) $customerId === (string) $customer->id ? 'selected' : '' }}>{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-secondary">Filter</button>
                <a href="{{ route('reports.sales') }}" class="btn btn-secondary">Clear</a>
            </form>

            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px;">
                <a class="btn btn-success" href="{{ route('reports.sales.excel', ['customer_id' => $customerId, 'from_date' => $fromDate, 'to_date' => $toDate]) }}">Export to Excel</a>
                <a class="btn btn-primary" href="{{ route('reports.sales.pdf', ['customer_id' => $customerId, 'from_date' => $fromDate, 'to_date' => $toDate]) }}">Download PDF</a>
            </div>

            <div class="stat-grid" style="margin-top:20px;">
                <div class="stat-card">
                    <div class="label">Total Invoices</div>
                    <div class="value">{{ $totals['count'] }}</div>
                </div>
                <div class="stat-card">
                    <div class="label">Sub Total</div>
                    <div class="value">&#8377;{{ number_format($totals['sub_total'], 2) }}</div>
                </div>
                @if($totals['discount_amount'] > 0)
                    <div class="stat-card">
                        <div class="label">Discount</div>
                        <div class="value">-&#8377;{{ number_format($totals['discount_amount'], 2) }}</div>
                    </div>
                    <div class="stat-card">
                        <div class="label">Total Amount</div>
                        <div class="value">&#8377;{{ number_format($totals['pre_gst_total'], 2) }}</div>
                    </div>
                @endif
                <div class="stat-card">
                    <div class="label">GST Collected</div><div class="value">&#8377;{{ number_format($totals['gst_amount'], 2) }}</div><small>CGST ₹{{ number_format($totals['cgst_amount'],2) }} · SGST ₹{{ number_format($totals['sgst_amount'],2) }} · IGST ₹{{ number_format($totals['igst_amount'],2) }}</small>
                </div>
                <div class="stat-card">
                    <div class="label">Net Amount</div>
                    <div class="value">&#8377;{{ number_format($totals['total_amount'], 2) }}</div>
                </div>
            </div>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Invoice No.</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Created By</th>
                            <th class="text-right">Sub Total</th>
                            <th class="text-right">Discount</th>
                            <th class="text-right">Total Amount</th>
                            <th class="text-right">CGST</th><th class="text-right">SGST</th><th class="text-right">IGST</th>
                            <th class="text-right">Net Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($invoices as $invoice)
                            <tr onclick="window.location='{{ route('invoices.show', $invoice) }}'" style="cursor:pointer;">
                                <td>{{ $invoice->invoice_number }}</td>
                                <td>{{ $invoice->invoice_date->format('d M Y') }}</td>
                                <td>{{ $invoice->customer->name }}</td>
                                <td>{{ $invoice->quotation->user->name ?? '-' }}</td>
                                <td class="text-right">&#8377;{{ number_format($invoice->sub_total, 2) }}</td>
                                <td class="text-right">{{ $invoice->discount_amount > 0 ? '-₹' . number_format($invoice->discount_amount, 2) : '-' }}</td>
                                <td class="text-right">&#8377;{{ number_format($invoice->sub_total - $invoice->discount_amount, 2) }}</td>
                                <td class="text-right">&#8377;{{ number_format($invoice->cgst_amount, 2) }}</td><td class="text-right">&#8377;{{ number_format($invoice->sgst_amount, 2) }}</td><td class="text-right">&#8377;{{ number_format($invoice->igst_amount, 2) }}</td>
                                <td class="text-right">&#8377;{{ number_format($invoice->total_amount, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center text-muted">No invoices found for this range.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection