@extends('layouts.app')
@section('title','Advance Ledger #'.$advance->id)
@section('content')
<style>
    .adv-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:16px; margin-bottom:20px; }
    .adv-stat { padding:14px 16px; }
    .adv-stat strong { display:block; font-size:13px; color:#6b7280; font-weight:600; margin-bottom:4px; }
    .adv-stat span { font-size:20px; font-weight:700; }
    .adv-return-form { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:12px; align-items:end; margin-bottom:24px; }
    .adv-return-form .form-group { margin:0; }
    .row-advance td { background:#f9fafb; font-weight:600; }
    .amt-out { color:#b91c1c; }
    .amt-in  { color:#166534; }
</style>

<div class="card">
    <div class="card-header">
        <h3>Advance Ledger #{{ $advance->id }} — {{ $advance->employee->name }} (ID {{ $advance->employee_id }})</h3>
        <a class="btn btn-secondary btn-sm" href="{{ route('employee-advances.index') }}">← Back</a>
    </div>
    <div class="card-body">

        <div class="adv-stats">
            <div class="card adv-stat"><strong>Advance Date</strong><span>{{ $advance->adv_date->format('d-m-Y') }}</span></div>
            <div class="card adv-stat"><strong>Advance Amount</strong><span>₹{{ number_format($advance->adv_amount, 2) }}</span></div>
            <div class="card adv-stat"><strong>Total Returned</strong><span>₹{{ number_format($returned, 2) }}</span></div>
            <div class="card adv-stat"><strong>Balance</strong><span>₹{{ number_format($balance, 2) }}</span></div>
        </div>

        @if($balance > 0)
            <h4 style="margin-bottom:10px">Add Return</h4>
            <form method="POST" action="{{ route('employee-advances.returns.store', $advance) }}" class="adv-return-form">
                @csrf
                <div class="form-group">
                    <label>Amount *</label>
                    <input class="form-control" type="number" name="amount" min="0.01" max="{{ $balance }}" step="0.01"
                           value="{{ old('amount') }}" placeholder="Max ₹{{ number_format($balance, 2) }}" required>
                    @error('amount')<small style="color:#b91c1c">{{ $message }}</small>@enderror
                </div>
                <div class="form-group">
                    <label>Return Date *</label>
                    <input class="form-control" type="date" name="return_date" min="{{ $advance->adv_date->format('Y-m-d') }}"
                           value="{{ old('return_date', date('Y-m-d')) }}" required>
                    @error('return_date')<small style="color:#b91c1c">{{ $message }}</small>@enderror
                </div>
                <div class="form-group">
                    <label>Note</label>
                    <input class="form-control" type="text" name="note" maxlength="255" value="{{ old('note') }}" placeholder="Optional">
                </div>
                <div class="form-group">
                    <button class="btn btn-primary">Add Return</button>
                </div>
            </form>
        @else
            <p class="text-muted" style="margin-bottom:20px">This advance is fully settled.</p>
        @endif

        <h4 style="margin-bottom:10px">Transactions</h4>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Particulars</th>
                        <th>Advance (Dr)</th>
                        <th>Return (Cr)</th>
                        <th>Balance</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="row-advance">
                        <td>—</td>
                        <td>{{ $advance->adv_date->format('d-m-Y') }}</td>
                        <td>Advance given</td>
                        <td class="amt-out">₹{{ number_format($advance->adv_amount, 2) }}</td>
                        <td>—</td>
                        <td>₹{{ number_format($advance->adv_amount, 2) }}</td>
                        <td></td>
                    </tr>
                    @forelse($returns as $i => $return)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $return->return_date->format('d-m-Y') }}</td>
                            <td>Return{{ $return->note ? ' — '.$return->note : '' }}</td>
                            <td>—</td>
                            <td class="amt-in">₹{{ number_format($return->amount, 2) }}</td>
                            <td>₹{{ number_format($return->running_balance, 2) }}</td>
                            <td>
                                <form method="POST" action="{{ route('employee-advances.returns.destroy', [$advance, $return]) }}"
                                      style="display:inline" data-confirm="Delete this return entry?">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted">No returns recorded yet.</td></tr>
                    @endforelse
                </tbody>
                @if($returns->count())
                    <tfoot>
                        <tr style="font-weight:700">
                            <td colspan="3" style="text-align:right">Total</td>
                            <td>₹{{ number_format($advance->adv_amount, 2) }}</td>
                            <td>₹{{ number_format($returned, 2) }}</td>
                            <td>₹{{ number_format($balance, 2) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection