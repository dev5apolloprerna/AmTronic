@extends('layouts.app')

@section('title', 'Payment Collection')

@section('content')
    <style>
        .payment-action-column,
        .history-action-column {
            width: 150px;
            min-width: 150px;
            text-align: center !important;
            white-space: nowrap;
        }

        .payment-action-column .btn,
        .history-action-column .btn {
            display: inline-block;
            min-width: 140px;
            text-align: center;
        }
    </style>

    <div class="card">
                <div class="card-header"><h3>Company-wise Payment Collection</h3></div>
    </div>

    <div class="stat-grid">
        <div class="stat-card"><div class="label">Total Receivable</div><div class="value">&#8377;{{ number_format($totals['billed'], 2) }}</div></div>
        <div class="stat-card"><div class="label">Total Collected</div><div class="value text-success">&#8377;{{ number_format($totals['collected'], 2) }}</div></div>
        <div class="stat-card"><div class="label">Total Pending</div><div class="value text-danger">&#8377;{{ number_format($totals['due'], 2) }}</div></div>
    </div>

    <div class="card">
        <div class="card-header"><h3>Company Outstanding</h3></div>
            <div class="card-body">
            <form method="GET" action="{{ route('payment-collections.index') }}" class="filters-bar">
                <div class="form-group">
                    <label for="companySearch">Search Company</label>
                    <input type="search" id="companySearch" name="company" value="{{ $companySearch }}" class="form-control" placeholder="Enter company name" maxlength="100" autocomplete="off" list="companySuggestions">
                    <datalist id="companySuggestions">
                        @foreach($companySuggestions as $companyName)
                            <option value="{{ $companyName }}"></option>
                        @endforeach
                    </datalist>
                </div>
                <button type="submit" class="btn btn-primary">Search</button>
                @if($companySearch !== '')
                    <a href="{{ route('payment-collections.index') }}" class="btn btn-secondary">Clear</a>
                @endif
            </form>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Company</th><th>Contact</th><th class="text-right">Receivable</th><th class="text-right">Collected</th><th class="text-right">Pending</th><th class="payment-action-column">Payment</th><th class="history-action-column">Payment History</th></tr></thead>
                <tbody>
                    @forelse($customers as $customer)
                        <tr>
                            <td><strong>{{ $customer->name }}</strong></td>
                            <td>{{ $customer->contact_person ?: '-' }}<br><span class="text-muted">{{ $customer->phone }}</span></td>
                            <td class="text-right">&#8377;{{ number_format($customer->billed_amount, 2) }}</td>
                            <td class="text-right text-success">&#8377;{{ number_format($customer->collected_amount, 2) }}</td>
                            <td class="text-right"><strong>&#8377;{{ number_format($customer->due_amount, 2) }}</strong></td>
                            <td class="payment-action-column">
                                @if($customer->due_amount > 0)
                                    <button
                                        type="button"
                                        class="btn btn-primary btn-sm js-collect"
                                        data-customer="{{ $customer->id }}"
                                        data-name="{{ $customer->name }}"
                                        data-due="{{ number_format($customer->due_amount, 2, '.', '') }}"
                                    >
                                        Collect Payment
                                    </button>
                                @else
                                    <span class="pill pill-approved">Paid</span>
                                @endif
                            </td>
                            <td class="history-action-column">
                                @if($customer->collected_amount > 0)
                                    <a
                                        class="btn btn-secondary btn-sm"
                                        href="{{ route('payment-collections.history', $customer) }}"
                                        aria-label="View payment history for {{ $customer->name }}"
                                    >
                                        <svg aria-hidden="true" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                        View History
                                    </a>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted">{{ $companySearch !== '' ? 'No companies match your search.' : 'No invoiced companies found.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    

    <div class="modal" id="collectModal" aria-hidden="true"><div class="modal-backdrop"></div><div class="modal-dialog">
        <form method="POST" action="{{ route('payment-collections.store') }}">@csrf
            <div class="modal-header"><h3>Collect Payment</h3><button class="modal-close" type="button">&times;</button></div>
            <div class="modal-body">
                <input type="hidden" name="customer_id" id="collectionCustomer">
                <p>Company: <strong id="collectionName"></strong><br><span class="text-muted">Pending: &#8377;<span id="collectionDue"></span></span></p>
                <div class="form-row"><div class="form-group"><label>Date *</label><input type="date" name="payment_date" value="{{ now()->toDateString() }}" class="form-control" required></div>
                <div class="form-group"><label>Amount *</label><input type="number" id="collectionAmount" name="amount" min="0.01" step="0.01" class="form-control" required></div></div>
                <div class="form-group"><label>Payment Method *</label><select name="payment_method" class="form-control" required><option value="cash">Cash</option><option value="upi">UPI</option><option value="bank_transfer">Bank Transfer</option><option value="cheque">Cheque</option><option value="other">Other</option></select></div>
                <div class="form-group"><label>Reference Number</label><input name="reference_number" class="form-control" maxlength="100"></div>
                <div class="form-group"><label>Notes</label><textarea name="notes" class="form-control" maxlength="1000"></textarea></div>
            </div><div class="modal-footer"><button type="button" class="btn btn-secondary modal-close">Cancel</button><button class="btn btn-success" type="submit">Collect & Generate Receipt</button></div>
        </form>
    </div></div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.js-collect').forEach(function (button) {
    button.addEventListener('click', function () {
        document.getElementById('collectionCustomer').value = button.dataset.customer;
        document.getElementById('collectionName').textContent = button.dataset.name;
        document.getElementById('collectionDue').textContent = Number(button.dataset.due).toLocaleString('en-IN', {minimumFractionDigits: 2});
        /*const amount = document.getElementById('collectionAmount'); amount.value = button.dataset.due; amount.max = button.dataset.due;*/
        document.getElementById('collectionAmount').value = button.dataset.due;
        document.getElementById('collectModal').classList.add('is-open'); document.body.classList.add('modal-open');
    });
});
document.querySelectorAll('#collectModal .modal-close, #collectModal .modal-backdrop').forEach(function (button) { button.addEventListener('click', function () { document.getElementById('collectModal').classList.remove('is-open'); document.body.classList.remove('modal-open'); }); });
</script>
@endpush
