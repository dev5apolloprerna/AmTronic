@extends('layouts.app')

@section('title', 'Employees')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Employees</h3>
            <a href="{{ route('users.create') }}" class="btn btn-primary btn-sm">+ Add Employee</a>
        </div>
        <div class="card-body">
            <form method="GET" class="filters-bar">
                <div class="form-group">
                    <label>Search</label>
                    <input type="text" name="search" class="form-control" value="{{ $search }}" placeholder="Name, email or designation...">
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
                            <th>Designation</th>
                            <th>Role</th>
                            <th>Can Log In</th>
                            <th class="text-right">Advance Balance</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $user)
                            <tr>
                                <td>{{ $user->name }}</td>
                                <td>{{ $user->email ?: '-' }}</td>
                                <td>{{ $user->designation?->name ?: '-' }}</td>
                                <td>{{ $user->role === 'super_admin' ? 'Super Admin' : 'User' }}</td>
                                <td>{{ $user->canLogin() ? 'Yes' : 'No' }}</td>
                                @php
                                    $given = (float) ($user->advance_given ?? 0);
                                    $returned = (float) ($user->advance_returned ?? 0);
                                @endphp
                                <td class="text-right">
                                    &#8377;{{ number_format($given - $returned, 2) }}
                                    @if($given > 0)
                                        <div class="form-hint">Given &#8377;{{ number_format($given, 2) }} &middot; Returned &#8377;{{ number_format($returned, 2) }}</div>
                                    @endif
                                </td>
                                <td><span class="pill pill-{{ $user->status }}">{{ $user->status }}</span></td>
                                <td>
                                    <a href="{{ route('users.edit', $user) }}" class="btn btn-secondary btn-sm">Edit</a>
                                    @if($user->id !== auth()->id())
                                        <form method="POST" action="{{ route('users.destroy', $user) }}" style="display:inline;" data-confirm="Delete this employee?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted">No employees found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrap">{{ $users->links() }}</div>
        </div>
    </div>
@endsection
