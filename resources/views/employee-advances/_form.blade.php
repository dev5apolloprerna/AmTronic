@php $a=$advance??null; @endphp
<div class="form-row">
	<div class="form-group"><label>Employee *</label>
		<select class="form-control" name="employee_id" required>
			<option value="">Select employee</option>
			@foreach($employees as $employee)
			<option value="{{ $employee->id }}" @selected(old('employee_id',$a->employee_id??'')==$employee->id)>{{ $employee->name }} (ID {{ $employee->id }})</option>
			@endforeach</select></div><div class="form-group">
				<label>Advance Amount *</label>
				<input type="number" min="0.01" step="0.01" class="form-control" name="adv_amount" value="{{ old('adv_amount',$a->adv_amount??'') }}" required>
			</div>
			<div class="form-group">
				<label>Advance Date *</label>
				<input type="date" class="form-control" name="adv_date" value="{{ old('adv_date',$a?->adv_date?->format('Y-m-d')??date('Y-m-d')) }}" required>
			</div>
		<!-- </div>
     <div class="form-row">
	    <div class="form-group">
	    	<label>Return Amount</label>
	    	<input type="number" min="0" step="0.01" class="form-control" name="return_amount" value="{{ old('return_amount', ($a && (float) $a->return_amount > 0) ? $a->return_amount : '') }}">
	    </div>
	    <div class="form-group">
	    	<label>Return Date</label>
	    	<input type="date" class="form-control" name="return_date" value="{{ old('return_date',$a?->return_date?->format('Y-m-d')) }}">
	    	<small class="text-muted">Required when a return amount is entered.</small>
	    </div> -->
	</div>
