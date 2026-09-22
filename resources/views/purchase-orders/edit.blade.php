@extends('layouts.app') @section('title','Edit Purchase Order') @section('content')<div class="card">
    <div class="card-header">
        <h3>Edit Purchase Order</h3><a class="btn btn-secondary btn-sm"
            href="{{ route('purchase-orders.show',$purchaseOrder) }}">← Back</a>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('purchase-orders.update',$purchaseOrder) }}">@csrf @method('PUT')
            @include('purchase-orders._form')<button class="btn btn-primary" style="margin-top:16px">Update Purchase
                Order</button></form>
    </div>
</div>@endsection