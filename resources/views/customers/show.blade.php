@extends('layouts.app')

@section('title', 'Company: ' . $customer->name)

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>{{ $customer->name }}</h3>
            <div>
                @if(auth()->user()->isSuperAdmin())
                    <a href="{{ route('customers.edit', $customer) }}" class="btn btn-secondary btn-sm">Edit</a>
                @endif
                <a href="{{ route('customers.index') }}" class="btn btn-secondary btn-sm">&larr; Back</a>
            </div>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div>
                    <p class="text-muted mb-0">Contact Person</p>
                    <p>{{ $customer->contact_person ?: '-' }}</p>
                </div>
                <div>
                    <p class="text-muted mb-0">Phone</p>
                    <p>{{ $customer->phone ?: '-' }}</p>
                </div>
                <div>
                    <p class="text-muted mb-0">Email</p>
                    <p>{{ $customer->email ?: '-' }}</p>
                </div>
                <div>
                    <p class="text-muted mb-0">GST Number</p>
                    <p>{{ $customer->gst_number ?: '-' }}</p>
                </div>
            </div>
            <div>
                <p class="text-muted mb-0">Current Balance</p>
                <p style="font-size:20px; font-weight:700;">
                    @if($balance > 0)
                        <span class="text-danger">&#8377;{{ number_format($balance, 2) }} Due</span>
                    @elseif($balance < 0)
                        <span class="text-success">&#8377;{{ number_format(abs($balance), 2) }} Advance</span>
                    @else
                        Settled
                    @endif
                </p>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>Add Ledger Entry</h3></div>
        <div class="card-body">
            <form method="POST" action="{{ route('customers.ledger.store', $customer) }}">
                @csrf
                <div class="form-row">
                    <div class="form-group">
                        <label>Entry Type *</label>
                        <select name="entry_type" class="form-control" required>
                            <option value="payment">Payment Received (reduces due)</option>
                            <option value="due_adjustment">Due Adjustment (increases due)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Amount (&#8377;) *</label>
                        <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Date *</label>
                        <input type="date" name="transaction_date" class="form-control" value="{{ now()->toDateString() }}" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <input type="text" name="description" class="form-control" placeholder="e.g. Cash payment, cheque no. 123...">
                </div>
                <button type="submit" class="btn btn-primary">Add Entry</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>Ledger History</h3></div>
        <div class="card-body table-wrap">
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
                        <td>{{ $customer->created_at->format('d M Y') }}</td>
                        <td>Opening Balance</td>
                        <td><span class="pill pill-settled">opening</span></td>
                        <td class="text-right">&#8377;{{ number_format($customer->opening_balance, 2) }}</td>
                        <td class="text-right">&#8377;{{ number_format($customer->opening_balance, 2) }}</td>
                        <td>{{ $customer->createdBy->name ?? '-' }}</td>
                    </tr>
                    @foreach($ledgers as $entry)
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
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if($quotations->count())
        <div class="card">
            <div class="card-header"><h3>Recent Quotations / Invoices</h3></div>
            <div class="card-body table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Number</th><th>Date</th><th>Status</th><th class="text-right">Total</th></tr>
                    </thead>
                    <tbody>
                        @foreach($quotations as $q)
                            <tr onclick="window.location='{{ route('quotations.show', $q) }}'" style="cursor:pointer;">
                                <td>{{ $q->quotation_number }}</td>
                                <td>{{ $q->quotation_date->format('d M Y') }}</td>
                                <td><span class="pill pill-{{ $q->status }}">{{ $q->status }}</span></td>
                                <td class="text-right">&#8377;{{ number_format($q->total_amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
