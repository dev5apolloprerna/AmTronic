@extends('layouts.app')

@section('title', 'Daily Attendance')

@section('content')
    <div class="card">
        <div class="card-header attendance-header">
            <div>
                <h3>Daily Employee Attendance</h3>
                <p class="text-muted mb-0">Select employees, choose a status, and mark attendance for the selected date.</p>
            </div>
            <form method="GET" action="{{ route('attendance.index') }}" class="attendance-date-form">
                <label for="attendance-view-date">Attendance Date</label>
                <input type="date" id="attendance-view-date" name="date" class="form-control" value="{{ $date }}" max="{{ now()->toDateString() }}">
                <button type="submit" class="btn btn-secondary">View</button>
            </form>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('attendance.store') }}" data-attendance-form>
                @csrf
                <input type="hidden" name="attendance_date" value="{{ $date }}">

                <div class="attendance-toolbar">
                    <div class="form-group">
                        <label for="attendance-status">Mark Selected As</label>
                        <select name="status" id="attendance-status" class="form-control" required>
                            <option value="">Choose status</option>
                            <option value="present" {{ old('status') === 'present' ? 'selected' : '' }}>Present</option>
                            <option value="absent" {{ old('status') === 'absent' ? 'selected' : '' }}>Absent</option>
                            <option value="half_day" {{ old('status') === 'half_day' ? 'selected' : '' }}>Half Day</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Mark Attendance</button>
                    <span class="text-muted" data-selected-count>0 employees selected</span>
                </div>

                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th class="attendance-checkbox-cell">
                                    <input type="checkbox" id="select-all-employees" aria-label="Select all employees">
                                </th>
                                <th>Employee</th>
                                <th>Designation</th>
                                <th>Employee Status</th>
                                <th>Attendance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($employees as $employee)
                                @php($record = $attendance->get($employee->id))
                                <tr>
                                    <td class="attendance-checkbox-cell">
                                        <input
                                            type="checkbox"
                                            name="employee_ids[]"
                                            value="{{ $employee->id }}"
                                            class="employee-attendance-checkbox"
                                            aria-label="Select {{ $employee->name }}"
                                            {{ in_array($employee->id, old('employee_ids', [])) ? 'checked' : '' }}
                                        >
                                    </td>
                                    <td>{{ $employee->name }}</td>
                                    <td>{{ $employee->designation?->name ?? '—' }}</td>
                                    <td><span class="pill pill-{{ $employee->status }}">{{ ucfirst($employee->status) }}</span></td>
                                    <td>
                                        @if($record)
                                            <span class="pill pill-attendance-{{ $record->status }}">
                                                {{ $record->status === 'half_day' ? 'Half Day' : ucfirst($record->status) }}
                                            </span>
                                        @else
                                            <span class="text-muted">Not marked</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted">No employees found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
    </div>
@endsection
