@extends('layouts.app')

@section('title', 'Users')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Users</h3>
            <a href="{{ route('users.create') }}" class="btn btn-primary btn-sm">+ Add User</a>
        </div>
        <div class="card-body">
            <form method="GET" class="filters-bar">
                <div class="form-group">
                    <label>Search</label>
                    <input type="text" name="search" class="form-control" value="{{ $search }}" placeholder="Name or email...">
                </div>
                <button type="submit" class="btn btn-secondary">Filter</button>
                @if($search)
                    <a href="{{ route('users.index') }}" class="btn btn-secondary">Clear</a>
                @endif
            </form>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $user)
                            <tr>
                                <td>{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td>{{ $user->role === 'super_admin' ? 'Super Admin' : 'User' }}</td>
                                <td><span class="pill pill-{{ $user->status }}">{{ $user->status }}</span></td>
                                <td>
                                    <a href="{{ route('users.edit', $user) }}" class="btn btn-secondary btn-sm">Edit</a>
                                    @if($user->id !== auth()->id())
                                        <form method="POST" action="{{ route('users.destroy', $user) }}" style="display:inline;" data-confirm="Delete this user?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted">No users found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrap">{{ $users->links() }}</div>
        </div>
    </div>
@endsection
