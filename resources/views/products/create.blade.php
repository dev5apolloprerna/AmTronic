@extends('layouts.app')

@section('title', 'Add Product')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Add New Product</h3>
            <a href="{{ route('products.index') }}" class="btn btn-secondary btn-sm">&larr; Back</a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('products.store') }}">
                @csrf
                @include('products._form')
                <button type="submit" class="btn btn-primary">Save Product</button>
            </form>
        </div>
    </div>
@endsection
