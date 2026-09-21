@extends('layouts.app')

@section('title', 'Quotations')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Quotations &amp; Invoices</h3>
            <a href="{{ route('quotations.create') }}" class="btn btn-primary btn-sm">+ New Quotation</a>
        </div>
        <div class="card-body">
            <form method="GET" class="filters-bar">
                <div class="form-group">
                    <label>Search</label>
                    <input type="text" name="search" class="form-control" value="{{ $search }}" placeholder="Quotation # or customer...">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="">All</option>
                        <option value="draft" {{ $status === 'draft' ? 'selected' : '' }}>Quotation Created</option>
                        <option value="sent" {{ $status === 'sent' ? 'selected' : '' }}>Quotation Sent</option>
                        <option value="approved" {{ $status === 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="invoice_sent" {{ $status === 'invoice_sent' ? 'selected' : '' }}>Invoice Sent</option>
                        <option value="rejected" {{ $status === 'rejected' ? 'selected' : '' }}>Rejected</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-secondary">Filter</button>
                @if($search || $status)
                    <a href="{{ route('quotations.index') }}" class="btn btn-secondary">Clear</a>
                @endif
            </form>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Number</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Created By</th>
                            <th class="text-right">Total</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($quotations as $q)
                            <tr>
                                <td>{{ $q->quotation_number }}</td>
                                <td>{{ $q->customer->name }}</td>
                                <td>{{ $q->quotation_date->format('d M Y') }}</td>
                                <td><span class="pill pill-{{ $q->displayStatusClass() }}">{{ $q->displayStatus() }}</span></td>
                                <td>{{ $q->user->name }}</td>
                                <td class="text-right">&#8377;{{ number_format($q->total_amount, 2) }}</td>
                                <td>
                                    <a href="{{ route('quotations.show', $q) }}" class="btn btn-secondary btn-sm">View</a>
                                    @if($q->isEditable())
                                        <a href="{{ route('quotations.edit', $q) }}" class="btn btn-secondary btn-sm">Edit</a>
                                        <form method="POST" action="{{ route('quotations.reject', $q) }}" style="display:inline;" data-confirm="Reject this quotation?">
                                            @csrf
                                            <button type="submit" class="btn btn-warning btn-sm">Reject</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('quotations.destroy', $q) }}" style="display:inline;" data-confirm="Delete this quotation? @if($q->status === 'approved') This will also delete the generated invoice and ledger entry. @endif">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                     <form method="POST" action="{{ route('quotations.duplicate', $q) }}" style="display:inline;" data-confirm="Create a new quotation copied from this one?">
                                        @csrf
                                        <button type="submit" class="btn btn-secondary btn-sm">Copy</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted">No quotations found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrap">{{ $quotations->links() }}</div>
        </div>
    </div>
@endsection
