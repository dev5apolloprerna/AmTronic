@extends('layouts.app')

@section('title', 'Edit State')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>{{ $lockReason ? 'State Details' : 'Edit State' }}</h3>
            <a href="{{ route('states.index') }}" class="btn btn-secondary btn-sm">&larr; Back</a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('states.update', $state) }}">
                @csrf
                @method('PUT')
                @include('states._form')
                @unless($lockReason)
                    <button type="submit" class="btn btn-primary">Update State</button>
                @endunless
            </form>
        </div>
    </div>
@endsection
