@extends('layouts.app')

@section('title', 'Pending Amounts')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Customer Pending Amounts</h3>
            <a class="btn btn-success btn-sm" href="{{ route('pending-amounts.excel') }}">
                <i class="fa fa-file-excel-o" aria-hidden="true"></i> Export to Excel
            </a>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Customer Name</th>
                        <th class="text-right">Pending Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($customers as $customer)
                        <tr>
                            <td>{{ $customer->name }}</td>
                            <td class="text-right">&#8377;{{ number_format($customer->pending_amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="text-center text-muted">No pending amounts found.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <th>Total Pending Amount</th>
                        <th class="text-right">&#8377;{{ number_format($totalPending, 2) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endsection
