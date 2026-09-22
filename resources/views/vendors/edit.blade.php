@extends('layouts.app')
@section('title', 'Edit Vendor')
@section('content')
<div class="card">
    <div class="card-header"><h3>Edit Vendor</h3></div>
    <div class="card-body">
        <form method="POST" action="{{ route('vendors.update', $vendor) }}">
            @csrf @method('PUT')
            @include('vendors._form')
            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Update Vendor</button>
                <a class="btn btn-secondary" href="{{ route('vendors.index') }}">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
