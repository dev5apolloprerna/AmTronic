<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class SalarySlip extends Model
{
    public const SUBMITTED = 'submitted';

    public const PROCESSED = 'processed';

    protected $fillable = [
        'employee_id', 'year', 'month', 'days_in_month', 'monthly_salary', 'per_day',
        'full_days', 'half_days', 'absent_days', 'paid_leave', 'unpaid_leave', 'payable_days',
        'leave_deduction', 'deduction', 'deduction_reason', 'incentive', 'net_salary', 'submitted_by',
        'status', 'processed_at', 'processed_by',
    ];

    protected function casts(): array
    {
        return [
            'monthly_salary' => 'decimal:2',
            'per_day' => 'decimal:2',
            'paid_leave' => 'float',
            'unpaid_leave' => 'float',
            'payable_days' => 'float',
            'leave_deduction' => 'decimal:2',
            'deduction' => 'decimal:2',
            'incentive' => 'decimal:2',
            'net_salary' => 'decimal:2',
            'processed_at' => 'datetime',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function isProcessed(): bool
    {
        return $this->status === self::PROCESSED;
    }

    public static function monthIsProcessed(int $year, int $month): bool
    {
        return static::where('year', $year)->where('month', $month)->where('status', self::PROCESSED)->exists();
    }

    public function getPeriodLabelAttribute(): string
    {
        return Carbon::create($this->year, $this->month, 1)->format('F Y');
    }

    public function getSlipNumberAttribute(): string
    {
        return sprintf('SAL-%04d%02d-%04d', $this->year, $this->month, $this->id);
    }
}
