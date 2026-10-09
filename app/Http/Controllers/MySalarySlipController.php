<?php

namespace App\Http\Controllers;

use App\Models\SalarySlip;
use App\Support\SalarySlipDocument;
use Illuminate\Http\Request;

/** "My Salary Slips" for a logged-in employee: own, processed slips only. */
class MySalarySlipController extends Controller
{
    public function index(Request $request)
    {
        $slips = $this->ownSlips($request)->orderByDesc('year')->orderByDesc('month')->paginate(24);

        return view('salary.my-slips', compact('slips'));
    }

    public function show(Request $request, int $salarySlip)
    {
        $slip = $this->ownSlips($request)->findOrFail($salarySlip);

        return view('salary.slip', SalarySlipDocument::data($slip, false, route('my-salary-slips.index'))
            + ['downloadUrl' => route('my-salary-slips.download', $slip)]);
    }

    public function download(Request $request, int $salarySlip)
    {
        $slip = $this->ownSlips($request)->findOrFail($salarySlip);

        return SalarySlipDocument::response($slip, true);
    }

    private function ownSlips(Request $request)
    {
        return SalarySlip::where('employee_id', $request->user()->id)->where('status', SalarySlip::PROCESSED);
    }
}
