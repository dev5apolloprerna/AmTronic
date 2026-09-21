@extends('layouts.app')

@section('title', 'Invoice ' . $invoice->invoice_number)

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Invoice {{ $invoice->invoice_number }}</h3>
            <div>
                <a href="{{ route('invoices.download', $invoice) }}" class="btn btn-primary btn-sm">Download PDF</a> @if($invoice->document_status !== 'invoice_approved')<form method="POST" action="{{ route('invoices.mark-sent',$invoice) }}" style="display:inline" data-confirm="Send and approve this invoice?">@csrf<button class="btn btn-success btn-sm">Send &amp; Approve</button></form>@endif @if($invoice->deliveryChallan)<a class="btn btn-secondary btn-sm" href="{{ route('delivery-challans.show',$invoice->deliveryChallan) }}">Delivery Challan</a>@else<form method="POST" action="{{ route('delivery-challans.store',$invoice) }}" style="display:inline">@csrf<button class="btn btn-secondary btn-sm">Create Delivery Challan</button></form>@endif
                <a href="{{ route('quotations.show', $invoice->quotation) }}" class="btn btn-secondary btn-sm">&larr; Back to Quotation</a>
            </div>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div>
                    <p class="text-muted mb-0">Customer</p>
                    <p>{{ $invoice->customer->name }}</p>
                </div>
                <div>
                    <p class="text-muted mb-0">Invoice Date</p>
                    <p>{{ $invoice->invoice_date->format('d M Y') }}</p>
                </div>
                <!-- <div>
                    <p class="text-muted mb-0">Quotation No.</p>
                    <p>{{ $invoice->quotation->quotation_number }}</p>
                </div> -->
                <div>
                    <p class="text-muted mb-0">Payment Status</p>
                    <p>
                        @if($balanceDue <= 0)
                            <span class="pill pill-approved">Fully Paid{{ $balanceDue < 0 ? ' (Overpaid)' : '' }}</span>
                        @elseif($totalPaid > 0)
                            <span class="pill pill-draft">Partially Paid</span>
                        @else
                            <span class="pill pill-due">Unpaid</span>
                        @endif
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>Items</h3></div>
        <div class="card-body table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <!-- <th>Despatch To</th> -->
                        <th class="text-right">Size (Mtr)</th>
                        <th class="text-right"># Rolls</th>
                        <th class="text-right">Total Mtr</th>
                        <th class="text-right">Price/Mtr</th>
                        <th class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoice->quotation->items as $item)
                        <tr>
<!--                             <td>{{ $item->product->name }}</td>
                            <td>{{ $item->despatch_to ?: '-' }}</td> -->
                            <td>{{ $item->product->name }}<br><small>HSN: {{ $item->product->hsn_code }} · {{ $item->product->description }}</small></td>
                            <td class="text-right">{{ number_format($item->size_mtr, 2) }}</td>
                            <td class="text-right">{{ $item->no_of_rolls }}</td>
                            <td class="text-right">{{ number_format($item->total_mtr, 2) }}</td>
                            <td class="text-right">&#8377;{{ number_format($item->price_per_mtr, 2) }}</td>
                            <td class="text-right">&#8377;{{ number_format($item->amount, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="totals-box">
                <div class="row"><span>Sub Total</span><span>&#8377;{{ number_format($invoice->sub_total, 2) }}</span></div>
                @if($invoice->discount_amount > 0)
                    <div class="row"><span>Discount</span><span>-&#8377;{{ number_format($invoice->discount_amount, 2) }}</span></div>
                @endif
                @if($invoice->admin_charges > 0)
                    <div class="row"><span>Admin Charges</span><span>+&#8377;{{ number_format($invoice->admin_charges, 2) }}</span></div>
                @endif
                @if($invoice->material_handling_charges > 0)
                    <div class="row"><span>Material Handling Charges</span><span>+&#8377;{{ number_format($invoice->material_handling_charges, 2) }}</span></div>
                @endif
                @if($invoice->discount_amount > 0 || $invoice->admin_charges > 0 || $invoice->material_handling_charges > 0)
                    <div class="row"><span>Taxable Amount</span><span>&#8377;{{ number_format($invoice->sub_total - $invoice->discount_amount + $invoice->admin_charges + $invoice->material_handling_charges, 2) }}</span></div>
                @endif
                @if($invoice->gst_amount > 0)
                    @if($invoice->cgst_amount > 0)<div class="row"><span>CGST (9%)</span><span>&#8377;{{ number_format($invoice->cgst_amount,2) }}</span></div><div class="row"><span>SGST (9%)</span><span>&#8377;{{ number_format($invoice->sgst_amount,2) }}</span></div>@else<div class="row"><span>IGST (18%)</span><span>&#8377;{{ number_format($invoice->igst_amount,2) }}</span></div>@endif
                @endif
                @if($invoice->round_off != 0)
                    <div class="row"><span>Round Off</span><span>{{ $invoice->round_off > 0 ? '+' : '' }}&#8377;{{ number_format($invoice->round_off, 2) }}</span></div>
                @endif
                <div class="row grand"><span>Net Amount</span><span>&#8377;{{ number_format($invoice->total_amount, 2) }}</span></div>
                <div class="row"><span>Amount Received</span><span class="text-success">&#8377;{{ number_format($totalPaid, 2) }}</span></div>

            </div>
        </div>
    </div>

    <div class="card" id="collect-payment">
        <div class="card-header"><h3>Collect Payment</h3></div>
        <div class="card-body">
            @if($balanceDue > 0)
                <form method="POST" action="{{ route('payments.store', $invoice) }}">
                    @csrf
                    <div class="form-row">
                        <div class="form-group">
                            <label>Payment Date *</label>
                            <input type="date" name="payment_date" class="form-control" value="{{ now()->toDateString() }}" required>
                        </div>
                        <div class="form-group">
                            <label>Amount Received (&#8377;) *</label>
                            <input type="number" step="0.01" min="0.01" max="{{ $balanceDue }}" name="amount" class="form-control" value="{{ number_format($balanceDue, 2, '.', '') }}" required>
                            <div class="form-hint">Balance due: &#8377;{{ number_format($balanceDue, 2) }}</div>
                        </div>
                        <div class="form-group">
                            <label>Payment Method</label>
                            <select name="payment_method" class="form-control">
                                <option value="cash">Cash</option>
                                <option value="cheque">Cheque</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="upi">UPI</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Reference No.</label>
                            <input type="text" name="reference_number" class="form-control" placeholder="Cheque/UTR no...">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <input type="text" name="notes" class="form-control">
                    </div>
                    <button type="submit" class="btn btn-primary">Record Payment</button>
                </form>
            @else
                <p class="text-success mb-0">This invoice has been fully paid. No balance is due.</p>
            @endif
        </div>
    </div>

    @if($invoice->payments->count())
        <div class="card">
            <div class="card-header"><h3>Payment History</h3></div>
            <div class="card-body table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th class="text-right">Amount</th>
                            <th>Method</th>
                            <th>Reference</th>
                            <th>Notes</th>
                            <th>Collected By</th>
                            @if(auth()->user()->isSuperAdmin())
                                <th>Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoice->payments as $payment)
                            <tr>
                                <td>{{ $payment->payment_date->format('d M Y') }}</td>
                                <td class="text-right text-success">&#8377;{{ number_format($payment->amount, 2) }}</td>
                                <td style="text-transform:capitalize;">{{ str_replace('_', ' ', $payment->payment_method) ?: '-' }}</td>
                                <td>{{ $payment->reference_number ?: '-' }}</td>
                                <td class="wrap">{{ $payment->notes ?: '-' }}</td>
                                <td>{{ $payment->enteredBy->name ?? '-' }}</td>
                                @if(auth()->user()->isSuperAdmin())
                                    <td>
                                        <form method="POST" action="{{ route('payments.destroy', $payment) }}" data-confirm="Remove this payment entry?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm">Remove</button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
