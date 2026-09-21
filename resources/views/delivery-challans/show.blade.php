@extends('layouts.app')
@section('title', 'Delivery Challan '.$deliveryChallan->challan_number)
@section('content')<div class="card">
	<div class="card-header">
		<h3>{{ $deliveryChallan->challan_number }}</h3>
		<div><a class="btn btn-primary btn-sm"
				href="{{ route('delivery-challans.download',$deliveryChallan) }}">Download PDF</a><a
				class="btn btn-secondary btn-sm" href="{{ route('invoices.show',$deliveryChallan->invoice) }}">Back to
				Invoice</a></div>
	</div>
	<div class="card-body">
		<p><strong>Date:</strong> {{ $deliveryChallan->challan_date->format('d M Y') }}</p>
		<p><strong>Company:</strong> {{ $deliveryChallan->invoice->customer->name }}</p>
		<div class="table-wrap">
			<table class="table">
				<thead>
					<tr>
						<th>Product / Description</th>
						<th>HSN</th>
						<th>Size</th>
						<th>Rolls</th>
						<th>Total Mtr</th>
					</tr>
				</thead>
				<tbody>@foreach($deliveryChallan->invoice->quotation->items as $item)<tr>
						<td>{{ $item->product->name }}<br><small>{{ $item->product->description }}</small></td>
						<td>{{ $item->product->hsn_code }}</td>
						<td>{{ $item->size_mtr }}</td>
						<td>{{ $item->no_of_rolls }}</td>
						<td>{{ $item->total_mtr }}</td>
					</tr>@endforeach</tbody>
			</table>
		</div>
	</div>
</div>@endsection