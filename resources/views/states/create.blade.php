@extends('layouts.app')

@section('title', 'Add State')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Add New State</h3>
            <a href="{{ route('states.index') }}" class="btn btn-secondary btn-sm">&larr; Back</a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('states.store') }}">
                @csrf
                @include('states._form')
                <button type="submit" class="btn btn-primary">Save State</button>
            </form>
        </div>
    </div>
@endsection
