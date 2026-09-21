@extends('layouts.app')

@section('title', 'Materials')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Material Master</h3>
            <a href="{{ route('materials.create') }}" class="btn btn-primary btn-sm">+ Add Material</a>
        </div>
        <div class="card-body">
            <form method="GET" class="filters-bar">
                <div class="form-group">
                    <label>Search</label>
                    <input type="text" name="search" class="form-control" value="{{ $search }}" placeholder="Name, code, HSN...">
                </div>
                <button type="submit" class="btn btn-secondary">Filter</button>
                @if($search)
                    <a href="{{ route('materials.index') }}" class="btn btn-secondary">Clear</a>
                @endif
            </form>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Code</th>
                            <th>Unit</th>
                            <th>HSN</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($materials as $material)
                            <tr>
                                <td>{{ $material->name }}</td>
                                <td>{{ $material->code ?: '-' }}</td>
                                <td>{{ $material->unit }}</td>
                                <td>{{ $material->hsn_code }}</td>
                                <td><span class="pill pill-{{ $material->status }}">{{ $material->status }}</span></td>
                                <td>
                                    <a href="{{ route('materials.edit', $material) }}" class="btn btn-secondary btn-sm">Edit</a>
                                    <form method="POST" action="{{ route('materials.destroy', $material) }}" style="display:inline;" data-confirm="Delete this material?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted">No materials found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrap">{{ $materials->links() }}</div>
        </div>
    </div>
@endsection
