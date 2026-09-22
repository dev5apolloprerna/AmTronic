@extends('layouts.app') 
@section('title','Edit Employee Advance') 
@section('content')
<div class="card">
	<div class="card-header">
		<h3>Edit Employee Advance #{{ $advance->id }}</h3>
		<a class="btn btn-secondary btn-sm" href="{{ route('employee-advances.index') }}">← Back</a>
	</div>
	<div class="card-body">
		<form method="POST" action="{{ route('employee-advances.update',$advance) }}">
			@csrf @method('PUT') 
			@include('employee-advances._form')
			<button class="btn btn-primary">Update Entry</button>
		</form>
	</div>
</div>
@endsection
