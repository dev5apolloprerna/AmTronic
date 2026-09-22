@extends('layouts.app')
@section('title', 'Employee Attendance Report')
@section('content')
<div class="card">
    <div class="card-header"><h3>Employee Attendance Report</h3></div>
    <div class="card-body">
        <form method="GET" class="filters-bar">
            <div class="form-group"><label for="from_date">From Date</label><input type="date" id="from_date" name="from_date" class="form-control" value="{{ $fromDate }}"></div>
            <div class="form-group"><label for="to_date">To Date</label><input type="date" id="to_date" name="to_date" class="form-control" value="{{ $toDate }}"></div>
            <button class="btn btn-secondary">Search</button>
            <a class="btn btn-secondary" href="{{ route('reports.employee-attendance') }}">Clear</a>
        </form>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Employee Name</th><th class="text-right">Absent</th><th class="text-right">Present</th><th class="text-right">Half Day</th></tr></thead>
                <tbody>
                @forelse($employees as $employee)
                    <tr><td>{{ $employee->name }}</td><td class="text-right">{{ $employee->absent_count }}</td><td class="text-right">{{ $employee->present_count }}</td><td class="text-right">{{ $employee->half_day_count }}</td></tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted">No employees found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
