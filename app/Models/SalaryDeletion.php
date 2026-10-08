<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/** Audit record written whenever a month's salary slips are deleted. */
class SalaryDeletion extends Model
{
    protected $fillable = ['year', 'month', 'status', 'slip_count', 'net_total', 'reason', 'snapshot', 'deleted_by'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'net_total' => 'decimal:2'];
    }

    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function getPeriodLabelAttribute(): string
    {
        return Carbon::create($this->year, $this->month, 1)->format('F Y');
    }
}
