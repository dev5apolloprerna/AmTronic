@extends('layouts.app')

@section('title', 'Add Company')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Add New Company</h3>
            <a href="{{ route('customers.index') }}" class="btn btn-secondary btn-sm">&larr; Back</a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('customers.store') }}">
                @csrf
                @include('customers._form')
                <button type="submit" class="btn btn-primary">Save Company</button>
            </form>
        </div>
    </div>
@endsection
