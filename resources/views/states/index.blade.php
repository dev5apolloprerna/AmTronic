@extends('layouts.app')

@section('title', 'States')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>State Master</h3>
            <a href="{{ route('states.create') }}" class="btn btn-primary btn-sm">+ Add State</a>
        </div>
        <div class="card-body">
            <form method="GET" class="filters-bar">
                <div class="form-group">
                    <label>Search</label>
                    <input type="text" name="search" class="form-control" value="{{ $search }}" placeholder="State name...">
                </div>
                <button type="submit" class="btn btn-secondary">Filter</button>
                @if($search)
                    <a href="{{ route('states.index') }}" class="btn btn-secondary">Clear</a>
                @endif
            </form>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>State</th>
                            <th>Status</th>
                            <th>In Use</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($states as $state)
                            @php $locked = $state->lockReason(); @endphp
                            <tr>
                                <td>{{ $state->name }}@if($state->isHomeState()) <span class="text-muted">(home state)</span>@endif</td>
                                <td><span class="pill pill-{{ $state->status }}">{{ $state->status }}</span></td>
                                <td>{{ $state->usageCount() ?: '-' }}</td>
                                <td>
                                    <a href="{{ route('states.edit', $state) }}" class="btn btn-secondary btn-sm">{{ $locked ? 'View' : 'Edit' }}</a>
                                    @unless($locked)
                                        <form method="POST" action="{{ route('states.destroy', $state) }}" style="display:inline;" data-confirm="Delete this state?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                        </form>
                                    @endunless
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted">No states found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrap">{{ $states->links() }}</div>
        </div>
    </div>
@endsection
