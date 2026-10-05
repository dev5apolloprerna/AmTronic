@extends('layouts.app')
@section('title','Employee Advances')
@section('content')
<style>
    .adv-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:16px; margin-bottom:20px; }
    .adv-stat { padding:14px 16px; }
    .adv-stat strong { display:block; font-size:13px; color:#6b7280; font-weight:600; margin-bottom:4px; }
    .adv-stat span { font-size:20px; font-weight:700; }
    .adv-badge { display:inline-block; padding:3px 10px; border-radius:999px; font-size:12px; font-weight:600; white-space:nowrap; }
    .adv-badge.open { background:#fee2e2; color:#b91c1c; }
    .adv-badge.partial { background:#fef3c7; color:#92400e; }
    .adv-badge.settled { background:#dcfce7; color:#166534; }
    .adv-actions { display:flex; gap:6px; align-items:center; white-space:nowrap; }
    .adv-actions form { display:inline; margin:0; }
    .btn-icon { display:inline-flex; align-items:center; gap:4px; }
    .btn-icon svg { width:15px; height:15px; }
</style>

<div class="card">
    <div class="card-header">
        <h3>Employee Advances</h3>
        <a class="btn btn-primary btn-sm" href="{{ route('employee-advances.create') }}">+ Add Advance</a>
    </div>
    <div class="card-body">
        <form method="GET" class="filters-bar">
            <div class="form-group">
                <label>Employee</label>
                <select class="form-control" name="employee_id">
                    <option value="">All employees</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}" @selected($employeeId == $employee->id)>{{ $employee->name }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn btn-secondary">Filter</button>
        </form>

        <div class="adv-stats">
            <div class="card adv-stat"><strong>Total Advanced</strong><span>₹{{ number_format($totals->advanced, 2) }}</span></div>
            <div class="card adv-stat"><strong>Total Returned</strong><span>₹{{ number_format($totals->returned, 2) }}</span></div>
            <div class="card adv-stat"><strong>Outstanding</strong><span>₹{{ number_format($totals->advanced - $totals->returned, 2) }}</span></div>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Emp ID</th>
                        <th>Employee</th>
                        <th>Advance</th>
                        <th>Advance Date</th>
                        <th>Returned</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($advances as $advance)
                    @php
                        $returned = (float) ($advance->returns_sum_amount ?? 0);
                        $balance  = round((float) $advance->adv_amount - $returned, 2);
                        $status   = $balance <= 0 ? 'settled' : ($returned > 0 ? 'partial' : 'open');
                    @endphp
                    <tr>
                        <td>{{ $advance->id }}</td>
                        <td>{{ $advance->employee_id }}</td>
                        <td>{{ $advance->employee->name }}</td>
                        <td>₹{{ number_format($advance->adv_amount, 2) }}</td>
                        <td>{{ $advance->adv_date->format('d-m-Y') }}</td>
                        <td>
                            ₹{{ number_format($returned, 2) }}
                            @if($advance->returns_count)
                                <small class="text-muted">({{ $advance->returns_count }} {{ Str::plural('entry', $advance->returns_count) }})</small>
                            @endif
                        </td>
                        <td><strong>₹{{ number_format($balance, 2) }}</strong></td>
                        <td><span class="adv-badge {{ $status }}">{{ ucfirst($status) }}</span></td>
                        <td>
                            <div class="adv-actions">
                                <a class="btn btn-primary btn-sm btn-icon" href="{{ route('employee-advances.ledger', $advance) }}" title="View / add returns">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                                        <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                                        <line x1="8" y1="7" x2="16" y2="7"/><line x1="8" y1="11" x2="16" y2="11"/>
                                    </svg>
                                    Ledger
                                </a>
                                <a class="btn btn-secondary btn-sm" href="{{ route('employee-advances.edit', $advance) }}">Edit</a>
                                <form method="POST" action="{{ route('employee-advances.destroy', $advance) }}" data-confirm="Delete this advance and all its return entries?">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted">No advance entries found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $advances->links() }}
    </div>
</div>
@endsection