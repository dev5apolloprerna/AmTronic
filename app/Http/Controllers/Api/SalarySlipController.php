<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalarySlip;
use App\Support\AmountInWords;
use App\Support\SalaryCalculator;
use App\Support\SalarySlipDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Salary slips for the logged-in employee (Android app).
 * Only processed (final) slips are visible; drafts stay with the admin.
 */
class SalarySlipController extends Controller
{
    /** POST /api/salary-slips/list   body: { "year": 2026 } (optional) */
    public function index(Request $request)
    {
        $data = $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,2100'],
        ]);

        $slips = $this->ownSlips($request)
            ->when($data['year'] ?? null, fn ($q, $year) => $q->where('year', $year))
            ->orderByDesc('year')->orderByDesc('month')
            ->get();

        return response()->json([
            'data' => $slips->map(fn (SalarySlip $slip) => $this->summary($slip))->values(),
            'years' => $this->ownSlips($request)->distinct()->orderByDesc('year')->pluck('year'),
        ]);
    }

    /** POST /api/salary-slips/{id}/show */
    public function show(Request $request, int $salarySlip)
    {
        $slip = $this->findOwn($request, $salarySlip);
        if (! $slip) {
            return response()->json(['message' => 'Salary slip not found.'], 404);
        }

        return response()->json(['data' => $this->detail($slip)]);
    }

    /**
     * GET or POST /api/salary-slips/{id}/pdf  (bearer token, or the signed pdf_url)
     * Opens inline; add ?download=1 to get it as a file download.
     */
    public function pdf(Request $request, int $salarySlip)
    {
        $slip = $this->findOwn($request, $salarySlip);
        if (! $slip) {
            return response()->json(['message' => 'Salary slip not found.'], 404);
        }

        return SalarySlipDocument::response($slip, $request->boolean('download'));
    }

    private function ownSlips(Request $request)
    {
        return SalarySlip::where('employee_id', $request->user()->id)
            ->where('status', SalarySlip::PROCESSED);
    }

    private function findOwn(Request $request, int $id): ?SalarySlip
    {
        return $this->ownSlips($request)->with('employee.designation')->find($id);
    }

    private function summary(SalarySlip $slip): array
    {
        return [
            'id' => $slip->id,
            'slip_number' => $slip->slip_number,
            'month' => $slip->month,
            'year' => $slip->year,
            'period' => $slip->period_label,
            'monthly_salary' => (float) $slip->monthly_salary,
            'total_deductions' => round((float) $slip->leave_deduction + (float) $slip->deduction, 2),
            'incentive' => (float) $slip->incentive,
            'net_salary' => (float) $slip->net_salary,
            'processed_at' => $slip->processed_at?->toIso8601String(),
            'pdf_url' => $this->pdfUrl($slip),
            'download_url' => $this->pdfUrl($slip, true),
        ];
    }

    private function detail(SalarySlip $slip): array
    {
        $company = config('invoice');

        return $this->summary($slip) + [
            'employee' => [
                'id' => $slip->employee_id,
                'name' => $slip->employee?->name,
                'designation' => $slip->employee?->designation?->name,
            ],
            'company' => [
                'name' => $company['company_name'],
                'address' => trim("{$company['address']}, {$company['city']}, {$company['state']} - {$company['postcode']}"),
            ],
            'attendance' => [
                'days_in_month' => $slip->days_in_month,
                'full_days' => $slip->full_days,
                'half_days' => $slip->half_days,
                'absent_days' => $slip->absent_days,
                'paid_leave' => (float) $slip->paid_leave,
                'unpaid_leave' => (float) $slip->unpaid_leave,
                'payable_days' => (float) $slip->payable_days,
                'paid_leaves_per_month' => SalaryCalculator::PAID_LEAVES_PER_MONTH,
            ],
            'per_day' => (float) $slip->per_day,
            'earnings' => [
                ['label' => 'Basic / Monthly Salary', 'amount' => (float) $slip->monthly_salary],
                ['label' => 'Incentive', 'amount' => (float) $slip->incentive],
            ],
            'deductions' => [
                ['label' => 'Leave Deduction', 'amount' => (float) $slip->leave_deduction, 'note' => SalaryCalculator::days((float) $slip->unpaid_leave).' unpaid day(s)'],
                ['label' => 'Other Deduction', 'amount' => (float) $slip->deduction, 'note' => $slip->deduction_reason],
            ],
            'total_earnings' => round((float) $slip->monthly_salary + (float) $slip->incentive, 2),
            'net_salary_in_words' => AmountInWords::rupees((float) $slip->net_salary),
        ];
    }

    /**
     * Signed link (no bearer token needed) so the app can hand it to a browser
     * or PDF viewer - same approach as quotation / invoice PDFs.
     */
    private function pdfUrl(SalarySlip $slip, bool $download = false): string
    {
        $parameters = ['salarySlip' => $slip->id, 'user' => $slip->employee_id] + ($download ? ['download' => 1] : []);
        $relative = URL::signedRoute('api.salary-slips.pdf', $parameters, absolute: false);

        return request()->getSchemeAndHttpHost().($relative[0] === '/' ? $relative : '/'.$relative);
    }
}
