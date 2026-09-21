@extends('layouts.app')

@section('title', 'Edit Designation')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Edit Designation</h3>
            <a href="{{ route('designations.index') }}" class="btn btn-secondary btn-sm">&larr; Back</a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('designations.update', $designation) }}">
                @csrf
                @method('PUT')
                @include('designations._form')
                <button type="submit" class="btn btn-primary">Update Designation</button>
            </form>
        </div>
    </div>
@endsection
