@extends('layouts.app')
@section('title', 'Salary Report')
@php($d = fn ($v) => \App\Support\SalaryCalculator::days($v))
@section('content')
<div class="card">
    <div class="card-header"><h3>Salary Report &mdash; {{ $periodLabel }}</h3></div>
    <div class="card-body">
        <form method="GET" class="filters-bar">
            <div class="form-group">
                <label for="month">Month</label>
                <select id="month" name="month" class="form-control">
                    @foreach(range(1, 12) as $m)
                        <option value="{{ $m }}" {{ $month === $m ? 'selected' : '' }}>{{ \Carbon\Carbon::create(null, $m, 1)->format('F') }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="year">Year</label>
                <select id="year" name="year" class="form-control">
                    @foreach(range(now()->year + 1, now()->year - 5) as $y)
                        <option value="{{ $y }}" {{ $year === $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn btn-secondary">Search</button>
            <a class="btn btn-secondary" href="{{ route('reports.salary') }}">Current Month</a>
        </form>

        <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px;">
            <a class="btn btn-success" href="{{ route('reports.salary.excel', ['month' => $month, 'year' => $year]) }}">Export to Excel</a>
        </div>

        <div class="stat-grid" style="margin-top:20px;">
            <div class="stat-card"><div class="label">Days in Month</div><div class="value">{{ $daysInMonth }}</div></div>
            <div class="stat-card"><div class="label">Gross Salary</div><div class="value">₹{{ number_format($totals->monthly_salary, 2) }}</div></div>
            <div class="stat-card"><div class="label">Leave Deduction</div><div class="value">₹{{ number_format($totals->deduction, 2) }}</div></div>
            <div class="stat-card"><div class="label">Net Payable</div><div class="value">₹{{ number_format($totals->net_salary, 2) }}</div></div>
        </div>

        <p class="text-muted" style="margin:14px 0 6px;">
            Leave = Absent + &frac12; per Half Day. First {{ \App\Support\SalaryCalculator::PAID_LEAVES_PER_MONTH }} leave days are paid; the rest are deducted at Monthly Salary &divide; {{ $daysInMonth }} per day. Days not marked are not deducted.
        </p>

        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Employee</th>
                    <th>Designation</th>
                    <th class="text-right">Monthly Salary</th>
                    <th class="text-right">Per Day</th>
                    <th class="text-right">Present</th>
                    <th class="text-right">Half Day</th>
                    <th class="text-right">Absent</th>
                    <th class="text-right">Not Marked</th>
                    <th class="text-right">Leave Days</th>
                    <th class="text-right">Paid Leave</th>
                    <th class="text-right">Unpaid Leave</th>
                    <th class="text-right">Payable Days</th>
                    <th class="text-right">Deduction</th>
                    <th class="text-right">Net Salary</th>
                </tr>
                </thead>
                <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>{{ $row->employee->name }}@if($row->monthly_salary <= 0) <span class="text-muted">(salary not set)</span>@endif</td>
                        <td>{{ $row->employee->designation?->name ?: '-' }}</td>
                        <td class="text-right">{{ number_format($row->monthly_salary, 2) }}</td>
                        <td class="text-right">{{ number_format($row->per_day, 2) }}</td>
                        <td class="text-right">{{ $row->present }}</td>
                        <td class="text-right">{{ $row->half_day }}</td>
                        <td class="text-right">{{ $row->absent }}</td>
                        <td class="text-right">{{ $row->not_marked }}</td>
                        <td class="text-right">{{ $d($row->leave_days) }}</td>
                        <td class="text-right">{{ $d($row->paid_leave) }}</td>
                        <td class="text-right">{{ $d($row->unpaid_leave) }}</td>
                        <td class="text-right">{{ $d($row->payable_days) }}</td>
                        <td class="text-right">{{ number_format($row->deduction, 2) }}</td>
                        <td class="text-right"><strong>{{ number_format($row->net_salary, 2) }}</strong></td>
                    </tr>
                @empty
                    <tr><td colspan="14" class="text-center text-muted">No employees found.</td></tr>
                @endforelse
                </tbody>
                @if($rows->isNotEmpty())
                <tfoot>
                <tr>
                    <th colspan="2">Total</th>
                    <th class="text-right">{{ number_format($totals->monthly_salary, 2) }}</th>
                    <th colspan="9"></th>
                    <th class="text-right">{{ number_format($totals->deduction, 2) }}</th>
                    <th class="text-right">{{ number_format($totals->net_salary, 2) }}</th>
                </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
