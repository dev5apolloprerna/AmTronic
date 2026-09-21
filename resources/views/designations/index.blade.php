@extends('layouts.app')

@section('title', 'Designations')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Employee Designations</h3>
            <a href="{{ route('designations.create') }}" class="btn btn-primary btn-sm">+ Add Designation</a>
        </div>
        <div class="card-body">
            <form method="GET" class="filters-bar">
                <div class="form-group">
                    <label>Search</label>
                    <input type="text" name="search" class="form-control" value="{{ $search }}" placeholder="Designation name...">
                </div>
                <button type="submit" class="btn btn-secondary">Filter</button>
                @if($search)
                    <a href="{{ route('designations.index') }}" class="btn btn-secondary">Clear</a>
                @endif
            </form>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Designation</th>
                            <th>Employees</th>
                            <th>Can Log In</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($designations as $designation)
                            <tr>
                                <td>{{ $designation->name }}</td>
                                <td>{{ $designation->employees_count }}</td>
                                <td>{{ $designation->can_login ? 'Yes' : 'No' }}</td>
                                <td><span class="pill pill-{{ $designation->status }}">{{ $designation->status }}</span></td>
                                <td>
                                    <a href="{{ route('designations.edit', $designation) }}" class="btn btn-secondary btn-sm">Edit</a>
                                    <form method="POST" action="{{ route('designations.destroy', $designation) }}" style="display:inline;" data-confirm="Delete this designation?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted">No designations found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrap">{{ $designations->links() }}</div>
        </div>
    </div>
@endsection
