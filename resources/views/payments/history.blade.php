@extends('layouts.app')

@section('title', 'Payment History')

@section('content')
    <div class="card">
        <div class="card-header">
            <div>
                <h3>{{ $customer->name }} - Payment History</h3>
                <div class="text-muted" style="margin-top: 5px;">
                    {{ $customer->contact_person ?: 'No contact person' }}
                    @if($customer->phone) &middot; {{ $customer->phone }} @endif
                </div>
            </div>
            <a href="{{ route('payment-collections.index') }}" class="btn btn-secondary btn-sm">&larr; Back to Payment Collection</a>
        </div>
    </div>

    <div class="stat-grid">
        <div class="stat-card">
            <div class="label">Total Payments</div>
            <div class="value">{{ $payments->count() }}</div>
        </div>
        <div class="stat-card">
            <div class="label">Total Collected</div>
            <div class="value text-success">&#8377;{{ number_format($totalCollected, 2) }}</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>All Payment Details</h3></div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Receipt No.</th>
                        <th>Invoice No.</th>
                        <th>Payment Method</th>
                        <th>Reference No.</th>
                        <th>Notes</th>
                        <th>Collected By</th>
                        <th class="text-right">Amount</th>
                        <th class="text-center">Receipt</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $payment)
                        <tr>
                            <td>{{ $payment->payment_date->format('d M Y') }}</td>
                            <td class="font-mono">{{ $payment->receipt_number ?: '-' }}</td>
                            <td>{{ $payment->invoice?->invoice_number ?: '-' }}</td>
                            <td>{{ str($payment->payment_method ?: 'Not specified')->replace('_', ' ')->title() }}</td>
                            <td>{{ $payment->reference_number ?: '-' }}</td>
                            <td class="wrap">{{ $payment->notes ?: '-' }}</td>
                            <td>{{ $payment->enteredBy?->name ?: '-' }}</td>
                            <td class="text-right"><strong>&#8377;{{ number_format((float) $payment->amount, 2) }}</strong></td>
                            <td class="text-center">
                                @if($payment->receipt_number)
                                    <a class="btn btn-secondary btn-sm" href="{{ route('payment-collections.receipt', $payment) }}">
                                        Download Receipt
                                    </a>
                                @else
                                    <span class="text-muted">Unavailable</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted">No payments have been collected for this company.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
