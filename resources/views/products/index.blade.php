@extends('layouts.app')

@section('title', 'Products')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Product Master</h3>
            <a href="{{ route('products.create') }}" class="btn btn-primary btn-sm">+ Add Product</a>
        </div>
        <div class="card-body">
            <form method="GET" class="filters-bar">
                <div class="form-group">
                    <label>Search</label>
                    <input type="text" name="search" class="form-control" value="{{ $search }}" placeholder="Name, code, HSN...">
                </div>
                <button type="submit" class="btn btn-secondary">Filter</button>
                @if($search)
                    <a href="{{ route('products.index') }}" class="btn btn-secondary">Clear</a>
                @endif
            </form>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Code</th>
                            <th>Unit</th>
                            <th>HSN</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($products as $product)
                            <tr>
                                <td><a href="{{ route('products.show', $product) }}">{{ $product->name }}</a></td>
                                <td>{{ $product->code ?: '-' }}</td>
                                <td>{{ $product->unit }}</td>
                                <td>{{ $product->hsn_code ?: '-' }}</td>
                                <td><span class="pill pill-{{ $product->status }}">{{ $product->status }}</span></td>
                                <td>
                                    <a href="{{ route('products.edit', $product) }}" class="btn btn-secondary btn-sm">Edit</a>
                                    <form method="POST" action="{{ route('products.destroy', $product) }}" style="display:inline;" data-confirm="Delete this product?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted">No products found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrap">{{ $products->links() }}</div>
        </div>
    </div>
@endsection
