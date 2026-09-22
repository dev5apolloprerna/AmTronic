@extends('layouts.app') 
@section('title','Employee Advances') 
@section('content')
<div class="card">
    <div class="card-header">
        <h3>Employee Advance Ledger</h3><a class="btn btn-primary btn-sm"
            href="{{ route('employee-advances.create') }}">+ Add Advance</a>
    </div>
    <div class="card-body">
        <form method="GET" class="filters-bar">
            <div class="form-group"><label>Employee</label><select class="form-control" name="employee_id">
                    <option value="">All employees</option>@foreach($employees as $employee)<option
                        value="{{ $employee->id }}" @selected($employeeId==$employee->id)>{{ $employee->name }}</option>
                    @endforeach
                </select></div><button class="btn btn-secondary">Filter</button>
        </form>
        <div class="form-row">
            <div class="card" style="padding:12px"><strong>Total Advanced</strong><span>₹{{
                    number_format($totals->advanced,2) }}</span></div>
            <div class="card" style="padding:12px"><strong>Total Returned</strong><span>₹{{
                    number_format($totals->returned,2) }}</span></div>
            <div class="card" style="padding:12px"><strong>Outstanding</strong><span>₹{{
                    number_format($totals->advanced-$totals->returned,2) }}</span></div>
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
                        <th>Return Date</th>
                        <th>Balance</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>@forelse($advances as $advance)<tr>
                        <td>{{ $advance->id }}</td>
                        <td>{{ $advance->employee_id }}</td>
                        <td>{{ $advance->employee->name }}</td>
                        <td>₹{{ number_format($advance->adv_amount,2) }}</td>
                        <td>{{ $advance->adv_date->format('d-m-Y') }}</td>
                        <td>₹{{ number_format($advance->return_amount,2) }}</td>
                        <td>{{ $advance->return_date?->format('d-m-Y')??'-' }}</td>
                        <td>₹{{ number_format($advance->adv_amount-$advance->return_amount,2) }}</td>
                        <td><a class="btn btn-secondary btn-sm"
                                href="{{ route('employee-advances.edit',$advance) }}">Edit</a>
                            <form method="POST" action="{{ route('employee-advances.destroy',$advance) }}"
                                style="display:inline" data-confirm="Delete this ledger entry?">@csrf
                                @method('DELETE')<button class="btn btn-danger btn-sm">Delete</button></form>
                        </td>
                    </tr>@empty<tr>
                        <td colspan="9" class="text-center text-muted">No advance entries found.</td>
                    </tr>@endforelse</tbody>
            </table>
        </div>{{ $advances->links() }}
    </div>
</div>
@endsection