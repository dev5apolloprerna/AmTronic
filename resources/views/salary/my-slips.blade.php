@extends('layouts.app')
@section('title', 'My Salary Slips')
@section('content')
<div class="card">
    <div class="card-header"><h3>My Salary Slips</h3></div>
    <div class="card-body">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Month</th>
                    <th>Slip No.</th>
                    <th class="text-right">Monthly Salary</th>
                    <th class="text-right">Deductions</th>
                    <th class="text-right">Incentive</th>
                    <th class="text-right">Net Salary</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse($slips as $slip)
                    <tr>
                        <td><strong>{{ $slip->period_label }}</strong></td>
                        <td>{{ $slip->slip_number }}</td>
                        <td class="text-right">{{ number_format($slip->monthly_salary, 2) }}</td>
                        <td class="text-right">{{ number_format((float) $slip->leave_deduction + (float) $slip->deduction, 2) }}</td>
                        <td class="text-right">{{ number_format($slip->incentive, 2) }}</td>
                        <td class="text-right"><strong>₹{{ number_format($slip->net_salary, 2) }}</strong></td>
                        <td>
                            <a class="btn btn-secondary btn-sm" href="{{ route('my-salary-slips.show', $slip->id) }}" target="_blank">View</a>
                            <a class="btn btn-primary btn-sm" href="{{ route('my-salary-slips.download', $slip->id) }}">Download PDF</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted">No salary slips yet. They appear here once salary is processed.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $slips->links() }}</div>
    </div>
</div>
@endsection
