@extends('layouts.app')

@section('title', 'Edit Product')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Edit Product</h3>
            <a href="{{ route('products.index') }}" class="btn btn-secondary btn-sm">&larr; Back</a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('products.update', $product) }}">
                @csrf
                @method('PUT')
                @include('products._form')
                <button type="submit" class="btn btn-primary">Update Product</button>
            </form>
        </div>
    </div>
@endsection
