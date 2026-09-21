@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="stat-grid">
        @if(auth()->user()->isSuperAdmin())
            <div class="stat-card">
                <div class="label">Total Customers</div>
                <div class="value">{{ $stats['total_customers'] }}</div>
            </div>
            <div class="stat-card">
                <div class="label">Total Products</div>
                <div class="value">{{ $stats['total_products'] }}</div>
            </div>
        @endif
        <div class="stat-card">
            <div class="label">Quotations Created</div>
            <div class="value">{{ $stats['draft_quotations'] }}</div>
        </div>
        <div class="stat-card">
            <div class="label">Quotations Sent</div>
            <div class="value">{{ $stats['sent_quotations'] }}</div>
        </div>
        <div class="stat-card">
            <div class="label">Approved Quotations</div>
            <div class="value">{{ $stats['approved_quotations'] }}</div>
        </div>
        <div class="stat-card">
            <div class="label">Total Invoiced Amount</div>
            <div class="value">&#8377;{{ number_format($stats['total_invoiced'], 2) }}</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Recent Quotations</h3>
            <a href="{{ route('quotations.create') }}" class="btn btn-primary btn-sm">+ New Quotation</a>
        </div>
        <div class="card-body table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Number</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Created By</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentQuotations as $q)
                        <tr onclick="window.location='{{ route('quotations.show', $q) }}'" style="cursor:pointer;">
                            <td>{{ $q->quotation_number }}</td>
                            <td>{{ $q->customer->name }}</td>
                            <td>{{ $q->quotation_date->format('d M Y') }}</td>
                            <td><span class="pill pill-{{ $q->displayStatusClass() }}">{{ $q->displayStatus() }}</span></td>
                            <td>{{ $q->user->name }}</td>
                            <td class="text-right">&#8377;{{ number_format($q->total_amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">No quotations yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if(auth()->user()->isSuperAdmin() && $outstandingCustomers->count())
        <div class="card">
            <div class="card-header">
                <h3>Top Outstanding Customers</h3>
                <a href="{{ route('reports.customer-ledger') }}" class="btn btn-secondary btn-sm">View Ledger Report</a>
            </div>
            <div class="card-body table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Customer</th><th class="text-right">Amount Due</th></tr>
                    </thead>
                    <tbody>
                        @foreach($outstandingCustomers as $c)
                            <tr>
                                <td><a href="{{ route('customers.show', $c) }}">{{ $c->name }}</a></td>
                                <td class="text-right text-danger">&#8377;{{ number_format($c->balance, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
