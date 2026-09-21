@extends('layouts.app')

@section('title', 'Add Employee')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Add New Employee</h3>
            <a href="{{ route('users.index') }}" class="btn btn-secondary btn-sm">&larr; Back</a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('users.store') }}" data-employee-form data-password-required="1">
                @csrf
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Name *</label>
                        <input type="text" class="form-control" id="name" name="name" value="{{ old('name') }}" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email <span data-login-required-mark>*</span></label>
                        <input type="email" class="form-control" id="email" name="email" value="{{ old('email') }}">
                    </div>
                </div>
                <div data-login-only>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="password">Password *</label>
                            <input type="password" class="form-control" id="password" name="password">
                        </div>
                        <div class="form-group">
                            <label for="password_confirmation">Confirm Password *</label>
                            <input type="password" class="form-control" id="password_confirmation" name="password_confirmation">
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="role">Role *</label>
                        <select class="form-control" id="role" name="role" required>
                            <option value="user" {{ old('role') === 'user' ? 'selected' : '' }}>User</option>
                            <option value="super_admin" {{ old('role') === 'super_admin' ? 'selected' : '' }}>Super Admin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="designation_id">Designation</label>
                        <select class="form-control" id="designation_id" name="designation_id">
                            <option value="">-- None --</option>
                            @foreach($designations as $designation)
                                <option value="{{ $designation->id }}" data-can-login="{{ $designation->can_login ? 1 : 0 }}" {{ (string) old('designation_id', '') === (string) $designation->id ? 'selected' : '' }}>{{ $designation->name }}{{ $designation->status === 'inactive' ? ' (inactive)' : '' }}</option>
                            @endforeach
                        </select>
                        <div class="form-hint">Only designations marked "can log in" (e.g. Sales) get login access.</div>
                    </div>
                    <div class="form-group">
                        <label for="status">Status *</label>
                        <select class="form-control" id="status" name="status" required>
                            <option value="active" {{ old('status', 'active') === 'active' ? 'selected' : '' }}>Active</option>
                            <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Save Employee</button>
            </form>
        </div>
    </div>
@endsection
