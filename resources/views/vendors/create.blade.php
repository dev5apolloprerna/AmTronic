@extends('layouts.app')
@section('title', 'Add Vendor')
@section('content')
<div class="card">
    <div class="card-header"><h3>Add Vendor</h3></div>
    <div class="card-body">
        <form method="POST" action="{{ route('vendors.store') }}">
            @csrf
            @include('vendors._form')
            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Save Vendor</button>
                <a class="btn btn-secondary" href="{{ route('vendors.index') }}">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
