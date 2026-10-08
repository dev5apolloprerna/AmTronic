<?php

namespace App\Support;

use App\Models\EmployeeAttendance;
use App\Models\SalarySlip;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Monthly salary from daily attendance.
 *
 *   Leave days       = absent + (half day x 0.5)
 *   Paid leave       = up to 2 leave days per month (no carry forward)
 *   Unpaid leave     = leave days - paid leave
 *   Per-day rate     = monthly salary / days in that month
 *   Leave deduction  = per-day rate x unpaid leave
 *   Net salary       = monthly salary - leave deduction - deduction + incentive
 *
 * "Deduction" (with its reason) and "Incentive" are entered by hand on the
 * salary sheet. Once a month is submitted they are read back from the saved
 * salary slip.
 *
 * Days with no attendance entry (Sundays, holidays, or days not yet marked)
 * are not deducted; they are only counted in "not_marked".
 */
class SalaryCalculator
{
    public const PAID_LEAVES_PER_MONTH = 2;

    public static function forMonth(int $year, int $month): array
    {
        $start = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $end = $start->endOfMonth()->startOfDay();
        $daysInMonth = $start->daysInMonth;

        $today = CarbonImmutable::today();
        $markableDays = match (true) {
            $start->greaterThan($today) => 0,
            $end->lessThanOrEqualTo($today) => $daysInMonth,
            default => $today->day,
        };

        $counts = EmployeeAttendance::query()
            ->whereDate('attendance_date', '>=', $start->toDateString())
            ->whereDate('attendance_date', '<=', $end->toDateString())
            ->selectRaw('employee_id, status, COUNT(*) as total')
            ->groupBy('employee_id', 'status')
            ->get()
            ->groupBy('employee_id');

        $slips = SalarySlip::where('year', $year)->where('month', $month)->get()->keyBy('employee_id');

        // Active employees, plus anyone with attendance or a slip this month.
        $employees = User::with('designation')
            ->where('role', 'user')
            ->where(fn ($q) => $q->where('status', 'active')
                ->orWhereIn('id', $counts->keys())
                ->orWhereIn('id', $slips->keys()))
            ->orderBy('name')
            ->get();

        $rows = $employees->map(function (User $employee) use ($counts, $slips, $daysInMonth, $markableDays) {
            $byStatus = ($counts->get($employee->id) ?? collect())->pluck('total', 'status');
            $present = (int) ($byStatus['present'] ?? 0);
            $halfDay = (int) ($byStatus['half_day'] ?? 0);
            $absent = (int) ($byStatus['absent'] ?? 0);
            $slip = $slips->get($employee->id);

            // A processed slip is final: show exactly what was paid.
            if ($slip?->isProcessed()) {
                $values = [
                    'monthly_salary' => (float) $slip->monthly_salary,
                    'per_day' => (float) $slip->per_day,
                    'present' => (int) $slip->full_days,
                    'half_day' => (int) $slip->half_days,
                    'absent' => (int) $slip->absent_days,
                    'leave_days' => $slip->absent_days + $slip->half_days * 0.5,
                    'paid_leave' => (float) $slip->paid_leave,
                    'unpaid_leave' => (float) $slip->unpaid_leave,
                    'payable_days' => (float) $slip->payable_days,
                    'leave_deduction' => (float) $slip->leave_deduction,
                    'earned_salary' => round((float) $slip->monthly_salary - (float) $slip->leave_deduction, 2),
                    'deduction' => (float) $slip->deduction,
                    'incentive' => (float) $slip->incentive,
                    'net_salary' => (float) $slip->net_salary,
                ];
            } else {
                $values = self::calculate(
                    (float) $employee->monthly_salary, $daysInMonth, $present, $halfDay, $absent,
                    (float) ($slip->deduction ?? 0), (float) ($slip->incentive ?? 0),
                );
            }

            // Submitted (draft) slip whose attendance or salary changed since it was saved.
            $outdated = $slip && ! $slip->isProcessed() && (
                $slip->full_days !== $present || $slip->half_days !== $halfDay || $slip->absent_days !== $absent
                || (float) $slip->monthly_salary !== (float) $employee->monthly_salary
            );

            return (object) array_merge($values, [
                'employee' => $employee,
                'slip' => $slip,
                'outdated' => $outdated,
                'not_marked' => max(0, $markableDays - ($present + $halfDay + $absent)),
                'deduction_reason' => $slip->deduction_reason ?? null,
            ]);
        });

        $processed = $slips->first(fn ($slip) => $slip->isProcessed());
        $status = match (true) {
            (bool) $processed => SalarySlip::PROCESSED,
            $slips->isNotEmpty() => SalarySlip::SUBMITTED,
            default => 'pending',
        };

        return [
            'rows' => $rows,
            'daysInMonth' => $daysInMonth,
            'periodLabel' => $start->format('F Y'),
            'isFuture' => $start->greaterThan($today),
            'submittedCount' => $slips->count(),
            'monthStatus' => $status,
            'processedSlip' => $processed?->loadMissing('processedBy'),
            'totals' => self::totals($rows),
        ];
    }

    /**
     * The whole salary for one employee and month. Used both for the sheet
     * and, server side, when the sheet is submitted.
     */
    public static function calculate(float $salary, int $daysInMonth, int $present, int $halfDay, int $absent, float $deduction = 0, float $incentive = 0): array
    {
        $leaveDays = $absent + $halfDay * 0.5;
        $paidLeave = (float) min(self::PAID_LEAVES_PER_MONTH, $leaveDays);
        $unpaidLeave = $leaveDays - $paidLeave;
        $perDay = $daysInMonth > 0 ? $salary / $daysInMonth : 0;
        $leaveDeduction = round($perDay * $unpaidLeave, 2);
        $gross = round($salary - $leaveDeduction, 2);

        return [
            'monthly_salary' => $salary,
            'per_day' => round($perDay, 2),
            'present' => $present,
            'half_day' => $halfDay,
            'absent' => $absent,
            'leave_days' => $leaveDays,
            'paid_leave' => $paidLeave,
            'unpaid_leave' => $unpaidLeave,
            'payable_days' => $daysInMonth - $unpaidLeave,
            'leave_deduction' => $leaveDeduction,
            'earned_salary' => $gross,
            'deduction' => round($deduction, 2),
            'incentive' => round($incentive, 2),
            'net_salary' => round($gross - $deduction + $incentive, 2),
        ];
    }

    private static function totals(Collection $rows): object
    {
        return (object) [
            'monthly_salary' => $rows->sum('monthly_salary'),
            'leave_deduction' => $rows->sum('leave_deduction'),
            'deduction' => $rows->sum('deduction'),
            'incentive' => $rows->sum('incentive'),
            'net_salary' => $rows->sum('net_salary'),
        ];
    }

    /** 1.5 => "1.5", 2.0 => "2" */
    public static function days(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
    }
}
