@extends('layouts.app') @section('title','New Purchase Order') @section('content')<div class="card">
    <div class="card-header">
        <h3>New Purchase Order</h3><a class="btn btn-secondary btn-sm" href="{{ route('purchase-orders.index') }}">←
            Back</a>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('purchase-orders.store') }}">@csrf @include('purchase-orders._form')<button
                class="btn btn-primary" style="margin-top:16px">Save Purchase Order</button></form>
    </div>
</div>@endsection