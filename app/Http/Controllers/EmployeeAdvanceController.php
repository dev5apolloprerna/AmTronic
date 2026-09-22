<?php

namespace App\Http\Controllers;

use App\Models\EmployeeAdvance;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeAdvanceController extends Controller
{
    public function index(Request $request)
    {
        $employeeId = $request->integer('employee_id') ?: null;
        $advances = EmployeeAdvance::with('employee')->when($employeeId, fn ($q) => $q->where('employee_id', $employeeId))->latest('adv_date')->paginate(25)->withQueryString();
        $employees = User::where('role', 'user')->orderBy('name')->get();
        $totals = EmployeeAdvance::when($employeeId, fn ($q) => $q->where('employee_id', $employeeId))->selectRaw('COALESCE(SUM(adv_amount),0) advanced, COALESCE(SUM(return_amount),0) returned')->first();
        return view('employee-advances.index', compact('advances','employees','employeeId','totals'));
    }
    public function create() { return view('employee-advances.create', ['employees' => User::where('role','user')->orderBy('name')->get()]); }
    public function store(Request $request) { EmployeeAdvance::create($this->validated($request)); return redirect()->route('employee-advances.index')->with('success','Employee advance recorded.'); }
    public function edit(EmployeeAdvance $employeeAdvance) { return view('employee-advances.edit', ['advance'=>$employeeAdvance,'employees'=>User::where('role','user')->orderBy('name')->get()]); }
    public function update(Request $request, EmployeeAdvance $employeeAdvance) { $employeeAdvance->update($this->validated($request)); return redirect()->route('employee-advances.index')->with('success','Employee advance updated.'); }
    public function destroy(EmployeeAdvance $employeeAdvance) { $employeeAdvance->delete(); return back()->with('success','Employee advance deleted.'); }
    private function validated(Request $request): array { return $request->validate([
        'employee_id'=>['required', Rule::exists('users', 'id')->where('role', 'user')], 'adv_amount'=>['required','numeric','gt:0'], 'adv_date'=>['required','date'],
        'return_amount'=>['nullable','numeric','gte:0','lte:'.$request->input('adv_amount',0)], 'return_date'=>['nullable','required_with:return_amount','date','after_or_equal:adv_date'],
    ]); }
}
