<?php

namespace App\Http\Controllers;

use App\Models\SalarySlip;
use App\Support\SalaryCalculator;
use App\Support\SalarySlipDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalaryController extends Controller
{
    /**
     * Editable salary sheet for one month: attendance-based salary plus a
     * deduction (with reason) and an incentive per employee.
     */
    public function index(Request $request)
    {
        return view('salary.index', $this->sheetData($request));
    }

    /**
     * Two actions share this form:
     *   submit  - save every row as a "submitted" slip; still editable.
     *   process - save every row and lock the month. Processed salary can't be
     *             edited; it has to be deleted (with a reason) and generated again.
     * Amounts are always recalculated here from attendance, never taken from the browser.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'action' => ['required', 'in:submit,process'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.employee_id' => ['required', 'integer', 'distinct'],
            'rows.*.deduction' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'rows.*.deduction_reason' => ['nullable', 'string', 'max:255'],
            'rows.*.incentive' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
        ], [
            'rows.*.deduction.min' => 'Deduction cannot be negative.',
            'rows.*.incentive.min' => 'Incentive cannot be negative.',
        ]);

        $month = (int) $data['month'];
        $year = (int) $data['year'];
        $sheet = SalaryCalculator::forMonth($year, $month);

        $process = $data['action'] === 'process';

        if ($sheet['isFuture']) {
            throw ValidationException::withMessages(['month' => 'Salary cannot be submitted for a future month.']);
        }
        if ($sheet['monthStatus'] === SalarySlip::PROCESSED) {
            throw ValidationException::withMessages(['month' => "Salary for {$sheet['periodLabel']} is already processed and locked. Use Delete & Regenerate to change it."]);
        }
        if ($process && count($data['rows']) !== $sheet['rows']->count()) {
            throw ValidationException::withMessages(['rows' => 'Every employee on the sheet must be included when processing salary. Reload the page and try again.']);
        }

        $sheetRows = $sheet['rows']->keyBy(fn ($row) => $row->employee->id);
        $errors = [];
        $slips = [];

        foreach ($data['rows'] as $index => $input) {
            $row = $sheetRows->get((int) $input['employee_id']);
            if (! $row) {
                $errors["rows.$index.employee_id"] = 'This employee is not on the salary sheet for this month.';
                continue;
            }

            $deduction = (float) ($input['deduction'] ?? 0);
            $incentive = (float) ($input['incentive'] ?? 0);
            $reason = trim((string) ($input['deduction_reason'] ?? '')) ?: null;

            if ($deduction > 0 && ! $reason) {
                $errors["rows.$index.deduction_reason"] = "Enter a deduction reason for {$row->employee->name}.";
            }

            $calc = SalaryCalculator::calculate(
                $row->monthly_salary, $sheet['daysInMonth'], $row->present, $row->half_day, $row->absent, $deduction, $incentive,
            );

            if ($calc['net_salary'] < 0) {
                $errors["rows.$index.deduction"] = "Net salary of {$row->employee->name} cannot be below zero.";
            }

            $slips[] = [
                'keys' => ['employee_id' => $row->employee->id, 'year' => $year, 'month' => $month],
                'values' => [
                    'days_in_month' => $sheet['daysInMonth'],
                    'monthly_salary' => $calc['monthly_salary'],
                    'per_day' => $calc['per_day'],
                    'full_days' => $calc['present'],
                    'half_days' => $calc['half_day'],
                    'absent_days' => $calc['absent'],
                    'paid_leave' => $calc['paid_leave'],
                    'unpaid_leave' => $calc['unpaid_leave'],
                    'payable_days' => $calc['payable_days'],
                    'leave_deduction' => $calc['leave_deduction'],
                    'deduction' => $calc['deduction'],
                    'deduction_reason' => $reason,
                    'incentive' => $calc['incentive'],
                    'net_salary' => $calc['net_salary'],
                    'submitted_by' => $request->user()->id,
                    'status' => $process ? SalarySlip::PROCESSED : SalarySlip::SUBMITTED,
                    'processed_at' => $process ? now() : null,
                    'processed_by' => $process ? $request->user()->id : null,
                ],
            ];
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($slips, $year, $month) {
            // Re-check inside the transaction so two admins can't process/submit over each other.
            if (SalarySlip::where('year', $year)->where('month', $month)->where('status', SalarySlip::PROCESSED)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['month' => 'Salary for this month was processed meanwhile.']);
            }
            foreach ($slips as $slip) {
                SalarySlip::updateOrCreate($slip['keys'], $slip['values']);
            }
        });

        $label = $sheet['periodLabel'];
        $count = count($slips);
        $message = $process
            ? "Salary for {$label} processed for {$count} employee(s). It is now locked and final salary slips are ready."
            : "Salary for {$label} submitted for {$count} employee(s). You can still change it until you process it.";

        return redirect()->route('reports.salary', ['month' => $month, 'year' => $year])->with('success', $message);
    }

    /**
     * Delete one employee's submitted (not processed) salary row, so it goes
     * back to the calculated values and can be entered again.
     */
    public function destroySlip(SalarySlip $salarySlip)
    {
        $route = ['month' => $salarySlip->month, 'year' => $salarySlip->year];

        if ($salarySlip->isProcessed()) {
            return redirect()->route('reports.salary', $route)
                ->with('error', 'Processed salary cannot be deleted row by row. Use Delete & Regenerate for the whole month.');
        }

        $name = $salarySlip->employee?->name ?? 'employee';
        $salarySlip->delete();

        return redirect()->route('reports.salary', $route)->with('success', "Submitted salary of {$name} deleted. Enter it again and submit.");
    }

    /**
     * Remove a whole month's salary so it can be generated again.
     *  - Submitted salary: discarded.
     *  - Processed salary: needs typing DELETE; unlocks the month and its attendance.
     */
    public function destroy(Request $request)
    {
        $data = $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'confirm' => ['nullable', 'string'],
        ]);
        $month = (int) $data['month'];
        $year = (int) $data['year'];
        $label = \Carbon\Carbon::create($year, $month, 1)->format('F Y');

        $slips = SalarySlip::where('year', $year)->where('month', $month);
        if (! (clone $slips)->exists()) {
            return redirect()->route('reports.salary', compact('month', 'year'))->with('error', "There is no salary saved for {$label}.");
        }

        $processed = (clone $slips)->where('status', SalarySlip::PROCESSED)->exists();
        if ($processed && strtoupper(trim((string) ($data['confirm'] ?? ''))) !== 'DELETE') {
            throw ValidationException::withMessages(['confirm' => 'Type DELETE to confirm.']);
        }

        $slips->delete();

        $message = $processed
            ? "Processed salary for {$label} deleted. Attendance is unlocked; correct it and generate the salary again."
            : "Submitted salary for {$label} discarded.";

        return redirect()->route('reports.salary', compact('month', 'year'))->with('success', $message);
    }

    public function excel(Request $request)
    {
        $data = $this->sheetData($request);
        $filename = sprintf('salary-%04d-%02d.xls', $data['year'], $data['month']);

        return response()
            ->view('salary.excel', $data)
            ->header('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    public function slip(SalarySlip $salarySlip)
    {
        return view('salary.slip', SalarySlipDocument::data(
            $salarySlip, false, route('reports.salary', ['month' => $salarySlip->month, 'year' => $salarySlip->year])
        ) + ['downloadUrl' => route('salary-slips.download', $salarySlip)]);
    }

    public function slipPdf(SalarySlip $salarySlip)
    {
        return SalarySlipDocument::response($salarySlip, true);
    }

    private function sheetData(Request $request): array
    {
        $filters = $request->validate([
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
        ]);
        $month = (int) ($filters['month'] ?? now()->month);
        $year = (int) ($filters['year'] ?? now()->year);

        return ['month' => $month, 'year' => $year] + SalaryCalculator::forMonth($year, $month);
    }
}
