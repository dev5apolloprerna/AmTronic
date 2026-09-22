@extends('layouts.app') 
@section('title','Add Employee Advance') 
@section('content')
<div class="card">
	<div class="card-header">
		<h3>Add Employee Advance</h3>
		<a class="btn btn-secondary btn-sm" href="{{ route('employee-advances.index') }}">← Back</a>
	</div>
	<div class="card-body">
		<form method="POST" action="{{ route('employee-advances.store') }}">
			@csrf 
			@include('employee-advances._form')
			<button class="btn btn-primary">Save Entry</button>
		</form>
	</div>
</div>
@endsection
