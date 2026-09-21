@extends('layouts.app')

@section('title', 'Edit Material')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Edit Material</h3>
            <a href="{{ route('materials.index') }}" class="btn btn-secondary btn-sm">&larr; Back</a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('materials.update', $material) }}">
                @csrf
                @method('PUT')
                @include('materials._form')
                <button type="submit" class="btn btn-primary">Update Material</button>
            </form>
        </div>
    </div>
@endsection
