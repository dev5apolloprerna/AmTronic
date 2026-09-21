@extends('layouts.app')

@section('title', 'Customer Ledger Report')

@section('content')
    <div class="card">
        <div class="card-header"><h3>Customer Ledger History</h3></div>
        <div class="card-body">
            <form method="GET" class="filters-bar">
                <div class="form-group">
                    <label>Customer *</label>
                    <select name="customer_id" class="form-control" required>
                        <option value="">Select customer</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" {{ (string) $customerId === (string) $customer->id ? 'selected' : '' }}>{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>From Date</label>
                    <input type="date" name="from_date" class="form-control" value="{{ $fromDate }}">
                </div>
                <div class="form-group">
                    <label>To Date</label>
                    <input type="date" name="to_date" class="form-control" value="{{ $toDate }}">
                </div>
                <button type="submit" class="btn btn-secondary">View Ledger</button>
            </form>

            @if($selectedCustomer)
 <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px;">
                    <a class="btn btn-success" href="{{ route('reports.customer-ledger.excel', ['customer_id' => $customerId, 'from_date' => $fromDate, 'to_date' => $toDate]) }}">Export to Excel</a>
                    <a class="btn btn-primary" href="{{ route('reports.customer-ledger.pdf', ['customer_id' => $customerId, 'from_date' => $fromDate, 'to_date' => $toDate]) }}">Download PDF</a>
                </div>
                
                <div class="stat-grid" style="margin-top:20px;">
                    <div class="stat-card">
                        <div class="label">Opening Balance (before range)</div>
                        <div class="value">&#8377;{{ number_format($openingBalanceBeforeRange, 2) }}</div>
                    </div>
                    <div class="stat-card">
                        <div class="label">Current Balance</div>
                        <div class="value">&#8377;{{ number_format($selectedCustomer->currentBalance(), 2) }}</div>
                    </div>
                </div>

                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Type</th>
                                <th class="text-right">Amount</th>
                                <th class="text-right">Balance After</th>
                                <th>Entered By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>-</td>
                                <td>Opening Balance {{ $fromDate ? '(as of start of range)' : '' }}</td>
                                <td><span class="pill pill-settled">opening</span></td>
                                <td class="text-right">-</td>
                                <td class="text-right">&#8377;{{ number_format($openingBalanceBeforeRange, 2) }}</td>
                                <td>-</td>
                            </tr>
                            @forelse($ledgers as $entry)
                                @if($entry->reference_type !== 'opening_balance')
                                    <tr>
                                        <td>{{ $entry->transaction_date->format('d M Y') }}</td>
                                        <td class="wrap">{{ $entry->description }}</td>
                                        <td><span class="pill pill-settled">{{ $entry->reference_type }}</span></td>
                                        <td class="text-right {{ $entry->amount >= 0 ? 'text-danger' : 'text-success' }}">
                                            {{ $entry->amount >= 0 ? '+' : '-' }}&#8377;{{ number_format(abs($entry->amount), 2) }}
                                        </td>
                                        <td class="text-right">&#8377;{{ number_format($entry->balance_after, 2) }}</td>
                                        <td>{{ $entry->enteredBy->name ?? '-' }}</td>
                                    </tr>
                                @endif
                            @empty
                                <tr><td colspan="6" class="text-center text-muted">No transactions in this range.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-muted">Select a customer to view their ledger history.</p>
            @endif
        </div>
    </div>
@endsection
