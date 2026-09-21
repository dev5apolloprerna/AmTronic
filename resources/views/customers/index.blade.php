@extends('layouts.app')

@section('title', 'Company')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Company</h3>
            <a href="{{ route('customers.create') }}" class="btn btn-primary btn-sm">+ Add Company</a>
        </div>
        <div class="card-body">
            <form method="GET" class="filters-bar">
                <div class="form-group">
                    <label>Search</label>
                    <input type="text" name="search" class="form-control" value="{{ $search }}" placeholder="Company name or state...">
                </div>
                <button type="submit" class="btn btn-secondary">Filter</button>
                @if($search)
                    <a href="{{ route('customers.index') }}" class="btn btn-secondary">Clear</a>
                @endif
            </form>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Company Name</th>
                            <th>State</th>
                            <th>Contact</th>
                            <th>GST No.</th>
                            <th class="text-right">Balance</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($customers as $customer)
                            <tr>
                                <td><a href="{{ route('customers.show', $customer) }}">{{ $customer->name }}</a></td>
                                <td>{{ $customer->state ?: '-' }}</td>
                                <td>{{ $customer->phone ?: '-' }}</td>
                                <td>{{ $customer->gst_number ?: '-' }}</td>
                                <td class="text-right">
                                    @if($customer->balance > 0)
                                        <span class="pill pill-due">Due &#8377;{{ number_format($customer->balance, 2) }}</span>
                                    @elseif($customer->balance < 0)
                                        <span class="pill pill-advance">Advance &#8377;{{ number_format(abs($customer->balance), 2) }}</span>
                                    @else
                                        <span class="pill pill-settled">Settled</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('customers.show', $customer) }}" class="btn btn-secondary btn-sm">View</a>
                                    <a href="{{ route('customers.edit', $customer) }}" class="btn btn-secondary btn-sm">Edit</a>
                                    <form method="POST" action="{{ route('customers.destroy', $customer) }}" style="display:inline;" data-confirm="Delete this customer? This will also permanently delete ALL their quotations, invoices, and payment history. This cannot be undone.">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted">No customers found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrap">{{ $customers->links() }}</div>
        </div>
    </div>
@endsection
