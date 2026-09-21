@extends('layouts.app')

@section('title', 'Quotation ' . $quotation->quotation_number)

@section('content')
    <div class="card">
         <div class="card-header quotation-header">
            <div class="quotation-heading">
                <h3>{{ $quotation->quotation_number }}</h3>
                <span class="pill pill-{{ $quotation->displayStatusClass() }}">{{ $quotation->displayStatus() }}</span>
            </div>
            <div class="quotation-actions">
                <div class="quotation-actions-group quotation-actions-workflow">
                    <a href="{{ route('quotations.download', $quotation) }}" target="_blank" class="btn btn-secondary btn-sm">Quotation PDF</a>
                @if($quotation->isEditable())
                    <form method="POST" action="{{ route('quotations.mark-sent', $quotation) }}" style="display:inline" data-confirm="Mark this quotation as sent? It can no longer be edited.">
                        @csrf
                        <button class="btn btn-success btn-sm">Send Quotation</button>
                    </form>
                @elseif($quotation->isSent())
                   <form method="POST" action="{{ route('quotations.approve', $quotation) }}" style="display:inline;" data-confirm="Approve this quotation?">
                        @csrf
                        <button type="submit" class="btn btn-success btn-sm">Approve Quotation</button>
                    </form>
                    @if(!$quotation->invoice)
                        <button type="button" class="btn btn-success btn-sm" data-modal-open="generateInvoiceModal">Generate Invoice</button>
                    @endif
                @endif
                @if($quotation->isEditable())
                    <a href="{{ route('quotations.edit', $quotation) }}" class="btn btn-secondary btn-sm">Edit</a>

                    <form method="POST" action="{{ route('quotations.reject', $quotation) }}" style="display:inline;" data-confirm="Reject this quotation?">
                        @csrf
                        <button type="submit" class="btn btn-warning btn-sm">Reject</button>
                    </form>
                    @endif
                @if(!$quotation->isEditable() && $quotation->invoice)
                    <a href="{{ route('invoices.show', $quotation->invoice) }}" class="btn btn-primary btn-sm">View Invoice</a>
                    <a href="{{ route('invoices.download', $quotation->invoice) }}" target="_blank" class="btn btn-secondary btn-sm">Download PDF</a>
                    @if($quotation->invoice->deliveryChallan)
                        <a href="{{ route('delivery-challans.show', $quotation->invoice->deliveryChallan) }}" class="btn btn-secondary btn-sm" target="_blank">Delivery Challan</a>
                    @else
                        <form method="POST" action="{{ route('delivery-challans.store', $quotation->invoice) }}" style="display:inline;">
                            @csrf
                            <button type="submit" class="btn btn-secondary btn-sm">Generate Delivery Challan</button>
                        </form>
                    @endif
                @endif

                @if($quotation->status === 'approved' && !$quotation->invoice)
                    <button type="button" class="btn btn-success btn-sm" data-modal-open="generateInvoiceModal">Generate Invoice</button>
                @endif
                </div>
                <div class="quotation-actions-group quotation-actions-manage">
                @if($quotation->status === 'approved')
                    <form method="POST" action="{{ route('quotations.destroy', $quotation) }}" style="display:inline;" data-confirm="Delete this approved quotation? This will also delete the generated invoice and ledger entry.">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('quotations.destroy', $quotation) }}" style="display:inline;" data-confirm="Delete this quotation?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('quotations.duplicate', $quotation) }}" style="display:inline;" data-confirm="Create a new quotation copied from this one?">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-sm">Copy</button>
                </form>
                <a href="{{ route('quotations.index') }}" class="btn btn-secondary btn-sm">&larr; Back</a>
            </div>
        </div>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div>
                    <p class="text-muted mb-0">Customer</p>
                    <p><a href="{{ route('customers.show', $quotation->customer) }}">{{ $quotation->customer->name }}</a></p>
                </div>
                <div>
                    <p class="text-muted mb-0">Quotation Date</p>
                    <p>{{ $quotation->quotation_date->format('d M Y') }}</p>
                </div>
                <div>
                    <p class="text-muted mb-0">Created By</p>
                    <p>{{ $quotation->user->name }}</p>
                </div>
                <div>
                    <p class="text-muted mb-0">GST</p>
                    <p>{{ $quotation->gst_applicable ? 'Applicable (18%)' : 'Not Applicable' }}</p>
                </div>
            </div>
            @if($quotation->status === 'approved')
                <div class="form-row">
                    <div>
                        <p class="text-muted mb-0">Approved By</p>
                        <p>{{ $quotation->approvedBy->name ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-muted mb-0">Approved At</p>
                        <p>{{ $quotation->approved_at->format('d M Y h:i A') }}</p>
                    </div>
                    <div>
                        <p class="text-muted mb-0">Invoice Number</p>
                        <p>{{ $quotation->invoice->invoice_number ?? '-' }}</p>
                    </div>
                    <div aria-hidden="true"></div>
                </div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>Items</h3></div>
        <div class="card-body table-wrap">
            <table class="table">
                 <thead>
                    <tr>
                        <th>Item</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Rate</th>
                        <th class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                     @foreach($quotation->items as $item)
                        <tr>
                            <td>
                                {{ $item->item_name }}
                                @if($item->legacy_size_label) - {{ $item->legacy_size_label }}@endif
                                <br><small>HSN: {{ $item->item_hsn ?: '-' }}@if($item->line_description) · {{ $item->line_description }}@endif</small>
                            </td>
                            <td class="text-right">{{ $item->qty_with_unit }}</td>
                            <td class="text-right">&#8377;{{ number_format($item->rate, 2) }}</td>
                            <td class="text-right">&#8377;{{ number_format($item->amount, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="totals-box">
                <div class="row"><span>Sub Total</span><span>&#8377;{{ number_format($quotation->sub_total, 2) }}</span></div>
                @if($quotation->discount_amount > 0)
                    <div class="row"><span>Discount</span><span>-&#8377;{{ number_format($quotation->discount_amount, 2) }}</span></div>
                @endif
                @if($quotation->admin_charges > 0)
                    <div class="row"><span>Admin Charges</span><span>+&#8377;{{ number_format($quotation->admin_charges, 2) }}</span></div>
                @endif
                @if($quotation->material_handling_charges > 0)
                    <div class="row"><span>Material Handling Charges</span><span>+&#8377;{{ number_format($quotation->material_handling_charges, 2) }}</span></div>
                @endif
                @if($quotation->discount_amount > 0 || $quotation->admin_charges > 0 || $quotation->material_handling_charges > 0)
                    <div class="row"><span>Taxable Amount</span><span>&#8377;{{ number_format($quotation->sub_total - $quotation->discount_amount + $quotation->admin_charges + $quotation->material_handling_charges, 2) }}</span></div>
                @endif
                @if($quotation->gst_applicable)
                    @if($quotation->cgst_amount > 0)<div class="row"><span>CGST (9%)</span><span>&#8377;{{ number_format($quotation->cgst_amount,2) }}</span></div><div class="row"><span>SGST (9%)</span><span>&#8377;{{ number_format($quotation->sgst_amount,2) }}</span></div>@else<div class="row"><span>IGST (18%)</span><span>&#8377;{{ number_format($quotation->igst_amount,2) }}</span></div>@endif
                @endif
                @if($quotation->round_off != 0)
                    <div class="row"><span>Round Off</span><span>{{ $quotation->round_off > 0 ? '+' : '' }}&#8377;{{ number_format($quotation->round_off, 2) }}</span></div>
                @endif
                <div class="row grand"><span>Net Amount</span><span>&#8377;{{ number_format($quotation->total_amount, 2) }}</span></div>
                <div class="row"><span>Previous Due</span><span class="{{ $previousDue > 0 ? 'text-danger' : 'text-success' }}">&#8377;{{ number_format($previousDue, 2) }}</span></div>
                 @if($quotation->invoice)
                    <div class="row"><span>Amount Received</span><span class="text-success">&#8377;{{ number_format($totalPaid, 2) }}</span></div>
                    <div class="row"><span>Balance Due</span><span class="{{ $balanceDue > 0 ? 'text-danger' : 'text-success' }}">&#8377;{{ number_format($balanceDue, 2) }}</span></div>
                @endif
            </div>
        </div>
    </div>

    @if(($quotation->isSent() || $quotation->status === 'approved') && !$quotation->invoice)
        <div id="generateInvoiceModal" class="modal {{ $errors->hasAny(['invoice_number', 'other_reference', 'invoice_date']) ? 'is-open' : '' }}" role="dialog" aria-modal="true" aria-labelledby="generateInvoiceTitle" aria-hidden="{{ $errors->hasAny(['invoice_number', 'other_reference', 'invoice_date']) ? 'false' : 'true' }}">
            <div class="modal-backdrop" data-modal-close></div>
            <div class="modal-dialog">
                <div class="modal-header">
                    <h3 id="generateInvoiceTitle">Generate Invoice</h3>
                    <button type="button" class="modal-close" data-modal-close aria-label="Close">&times;</button>
                </div>
                <form method="POST" action="{{ route('quotations.generate-invoice', $quotation) }}">
                    @csrf
                        <div class="modal-body">
                            <p class="text-muted">Enter an invoice number for sent quotation <strong>{{ $quotation->quotation_number }}</strong>.</p>
                            <div class="form-group">
                                <label for="invoice_number">Invoice Number <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="invoice_number" name="invoice_number" class="form-control" value="{{ old('invoice_number') }}" placeholder="Enter invoice number" maxlength="255" required autocomplete="off">
                                @error('invoice_number')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                        </div>
                        <div class="form-group">
                            <label for="other_reference">Reference Number</label>
                            <input type="text" id="other_reference" name="other_reference" class="form-control" value="{{ old('other_reference') }}" placeholder="Enter reference number" maxlength="255" autocomplete="off">
                            @error('other_reference')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>
                         <div class="form-group">
                            <label for="invoice_date">Invoice Date <span class="text-danger">*</span></label>
                            <input type="date" id="invoice_date" name="invoice_date" class="form-control" value="{{ old('invoice_date', now()->toDateString()) }}" required>
                            @error('invoice_date')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                        <button type="submit" class="btn btn-success">Generate Invoice</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

