<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeAdvanceReturn extends Model
{
    protected $fillable = ['amount', 'return_date', 'note'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'return_date' => 'date',
        ];
    }

    public function advance()
    {
        return $this->belongsTo(EmployeeAdvance::class, 'employee_advance_id');
    }
}
