@extends('layouts.app')

@section('title', 'Add Material')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Add New Material</h3>
            <a href="{{ route('materials.index') }}" class="btn btn-secondary btn-sm">&larr; Back</a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('materials.store') }}">
                @csrf
                @include('materials._form')
                <button type="submit" class="btn btn-primary">Save Material</button>
            </form>
        </div>
    </div>
@endsection
