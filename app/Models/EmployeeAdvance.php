<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeAdvance extends Model
{
    protected $fillable = [
        'employee_id',
        'adv_amount',
        'adv_date',
        'return_amount',
        'return_date',
    ];

    protected function casts(): array
    {
        return [
            'adv_amount' => 'decimal:2',
            'return_amount' => 'decimal:2',
            'adv_date' => 'date',
            'return_date' => 'date',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
     public function returns()
    {
        return $this->hasMany(EmployeeAdvanceReturn::class)->orderBy('return_date')->orderBy('id');
    }

    public function getReturnedAmountAttribute(): string
    {
        return number_format((float) ($this->returns_sum_amount ?? $this->returns()->sum('amount')), 2, '.', '');
    }

    public function getBalanceAttribute(): string
    {
        return number_format((float) $this->adv_amount - (float) $this->returned_amount, 2, '.', '');
    }
}
