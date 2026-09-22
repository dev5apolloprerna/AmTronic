@extends('layouts.app')
@section('title', 'Employee Attendance History')
@section('content')
<div class="card">
    <div class="card-header"><h3>Employee Attendance History</h3></div>
    <div class="card-body">
        <form method="GET" class="filters-bar">
            <div class="form-group"><label for="from_date">From Date</label><input type="date" id="from_date" name="from_date" class="form-control" value="{{ $fromDate }}"></div>
            <div class="form-group"><label for="to_date">To Date</label><input type="date" id="to_date" name="to_date" class="form-control" value="{{ $toDate }}"></div>
            <div class="form-group">
                <label for="employee_id">Employee Name</label>
                <select id="employee_id" name="employee_id" class="form-control">
                    <option value="">All Employees</option>
                    @foreach($employees as $employee)<option value="{{ $employee->id }}" {{ (string) $employeeId === (string) $employee->id ? 'selected' : '' }}>{{ $employee->name }}</option>@endforeach
                </select>
            </div>
            <button class="btn btn-secondary">Search</button>
            <a class="btn btn-secondary" href="{{ route('reports.employee-attendance-history') }}">Clear</a>
        </form>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Employee Name</th><th>Date</th><th>Attendance Status</th></tr></thead>
                <tbody>
                @forelse($history as $attendance)
                    <tr>
                        <td>{{ $attendance->employee->name }}</td>
                        <td>{{ $attendance->attendance_date->format('d M Y') }}</td>
                        <td><span class="pill pill-{{ $attendance->status }}">{{ ucwords(str_replace('_', ' ', $attendance->status)) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-center text-muted">No attendance records found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $history->links() }}</div>
    </div>
</div>
@endsection
