@extends('layouts.app')
@section('title', 'Vendors')
@section('content')
<div class="card">
    <div class="card-header">
        <h3>Vendor Master</h3>
        <a class="btn btn-primary btn-sm" href="{{ route('vendors.create') }}">+ Add Vendor</a>
    </div>
    <div class="card-body">
        <form method="GET" class="filters-bar">
            <div class="form-group">
                <label for="search">Search</label>
                <input id="search" name="search" class="form-control" value="{{ $search }}" placeholder="Name, mobile or GST number">
            </div>
            <button class="btn btn-secondary">Filter</button>
            @if($search)<a class="btn btn-secondary" href="{{ route('vendors.index') }}">Clear</a>@endif
        </form>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Vendor</th><th>Contact Person</th><th>Mobile</th><th>Email</th><th>GST Number</th><th>Actions</th></tr></thead>
                <tbody>
                @forelse($vendors as $vendor)
                    <tr>
                        <td>{{ $vendor->name }}</td><td>{{ $vendor->contact_person ?: '-' }}</td><td>{{ $vendor->mobile }}</td>
                        <td>{{ $vendor->email ?: '-' }}</td><td>{{ $vendor->gst_number ?: '-' }}</td>
                        <td>
                            <a class="btn btn-secondary btn-sm" href="{{ route('vendors.edit', $vendor) }}">Edit</a>
                            <form method="POST" action="{{ route('vendors.destroy', $vendor) }}" style="display:inline" data-confirm="Delete this vendor?">
                                @csrf @method('DELETE')<button class="btn btn-danger btn-sm">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted">No vendors found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $vendors->links() }}</div>
    </div>
</div>
@endsection
