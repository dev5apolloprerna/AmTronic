@extends('layouts.app')

@section('title', 'Product: ' . $product->name)

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>{{ $product->name }}</h3>
            <div>
                <a href="{{ route('products.edit', $product) }}" class="btn btn-secondary btn-sm">Edit</a>
                <a href="{{ route('products.index') }}" class="btn btn-secondary btn-sm">&larr; Back</a>
            </div>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div>
                    <p class="text-muted mb-0">Code</p>
                    <p>{{ $product->code ?: '-' }}</p>
                </div>
                <div>
                    <p class="text-muted mb-0">Unit</p>
                    <p>{{ $product->unit }}</p>
                </div>
                <div>
                    <p class="text-muted mb-0">HSN Code</p>
                    <p>{{ $product->hsn_code ?: '-' }}</p>
                </div>
                <div>
                    <p class="text-muted mb-0">Status</p>
                    <p><span class="pill pill-{{ $product->status }}">{{ $product->status }}</span></p>
                </div>
            </div>
            <div>
                <p class="text-muted mb-0">Description</p>
                <p>{{ $product->description ?: '-' }}</p>
            </div>
        </div>
    </div>
@endsection
