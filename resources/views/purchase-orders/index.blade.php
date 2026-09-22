@extends('layouts.app')
@section('title','Purchase Orders')
@section('content')
<div class="card">
    <div class="card-header">
        <h3>Manage Purchase Orders</h3><a class="btn btn-primary btn-sm" href="{{ route('purchase-orders.create') }}">+
            New PO</a>
    </div>
    <div class="card-body">
        <form method="GET" class="filters-bar">
            <div class="form-group"><label for="search">Search</label><input id="search" name="search"
                    class="form-control" value="{{ $search }}" placeholder="PO number or vendor"></div><button
                class="btn btn-secondary">Filter</button>
        </form>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>PO Number</th>
                        <th>Date</th>
                        <th>Vendor</th>
                        <th>Total</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)<tr>
                        <td>{{ $order->po_number }}</td>
                        <td>{{ $order->order_date->format('d-m-Y') }}</td>
                        <td>{{ $order->vendor->name }}</td>
                        <td>₹{{ number_format($order->total_amount,2) }}</td>
                        <td><a class="btn btn-primary btn-sm" href="{{ route('purchase-orders.show',$order) }}">View</a>
                            <a class="btn btn-secondary btn-sm"
                                href="{{ route('purchase-orders.edit',$order) }}">Edit</a> <a
                                class="btn btn-secondary btn-sm"
                                href="{{ route('purchase-orders.download',$order) }}">PDF</a>
                            <form method="POST" action="{{ route('purchase-orders.destroy',$order) }}"
                                style="display:inline" data-confirm="Delete this purchase order?">@csrf
                                @method('DELETE')<button class="btn btn-danger btn-sm">Delete</button></form>
                        </td>
                    </tr>
                    @empty<tr>
                        <td colspan="5" class="text-center text-muted">No purchase orders found.</td>
                    </tr>@endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $orders->links() }}</div>
    </div>
</div>
@endsection