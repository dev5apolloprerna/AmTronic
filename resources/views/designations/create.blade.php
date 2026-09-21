@extends('layouts.app')

@section('title', 'Add Designation')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Add New Designation</h3>
            <a href="{{ route('designations.index') }}" class="btn btn-secondary btn-sm">&larr; Back</a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('designations.store') }}">
                @csrf
                @include('designations._form')
                <button type="submit" class="btn btn-primary">Save Designation</button>
            </form>
        </div>
    </div>
@endsection
