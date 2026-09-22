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
                        <th>Return Ledger</th>
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
<!--                         <td>₹{{ number_format($advance->return_amount,2) }}</td>
                        <td>{{ $advance->return_date?->format('d-m-Y')??'-' }}</td>
                        <td>₹{{ number_format($advance->adv_amount-$advance->return_amount,2) }}</td> -->
                        <td>₹{{ number_format($advance->returned_amount,2) }}</td>
                        <td>
                            @forelse($advance->returns as $return)
                                <div style="white-space:nowrap; margin-bottom:4px">
                                    {{ $return->return_date->format('d-m-Y') }} — ₹{{ number_format($return->amount,2) }}
                                    @if($return->note)<span class="text-muted">({{ $return->note }})</span>@endif
                                    <form method="POST" action="{{ route('employee-advances.returns.destroy', [$advance, $return]) }}" style="display:inline" data-confirm="Delete this return entry?">
                                        @csrf @method('DELETE')<button class="btn btn-danger btn-sm" title="Delete return">×</button>
                                    </form>
                                </div>
                            @empty
                                <span class="text-muted">No returns</span>
                            @endforelse
                            @if((float) $advance->balance > 0)
                                <form method="POST" action="{{ route('employee-advances.returns.store', $advance) }}" style="margin-top:8px">
                                    @csrf
                                    <div style="display:flex; gap:4px; align-items:center; flex-wrap:wrap">
                                        <input class="form-control" style="width:110px" type="number" name="amount" min="0.01" max="{{ $advance->balance }}" step="0.01" placeholder="Amount" required>
                                        <input class="form-control" style="width:145px" type="date" name="return_date" min="{{ $advance->adv_date->format('Y-m-d') }}" value="{{ date('Y-m-d') }}" required>
                                        <input class="form-control" style="width:130px" type="text" name="note" maxlength="255" placeholder="Note">
                                        <button class="btn btn-primary btn-sm">Add Return</button>
                                    </div>
                                </form>
                            @endif
                        </td>
                        <td>₹{{ number_format($advance->balance,2) }}</td>
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