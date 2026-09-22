<?php

namespace App\Http\Controllers;

use App\Models\EmployeeAdvance;
use App\Models\EmployeeAdvanceReturn;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EmployeeAdvanceController extends Controller
{
    public function index(Request $request)
    {
        $employeeId = $request->integer('employee_id') ?: null;
        $advances = EmployeeAdvance::with(['employee', 'returns'])->withSum('returns', 'amount')
            ->when($employeeId, fn ($q) => $q->where('employee_id', $employeeId))
            ->latest('adv_date')->paginate(25)->withQueryString();

        $employees = User::where('role', 'user')->orderBy('name')->get();
        $advanceQuery = EmployeeAdvance::when($employeeId, fn ($q) => $q->where('employee_id', $employeeId));
        $totals = (object) [
            'advanced' => (clone $advanceQuery)->sum('adv_amount'),
            'returned' => EmployeeAdvanceReturn::whereIn('employee_advance_id', (clone $advanceQuery)->select('id'))->sum('amount'),
        ];

        return view('employee-advances.index', compact('advances','employees','employeeId','totals'));
    }
    public function create() { return view('employee-advances.create', ['employees' => User::where('role','user')->orderBy('name')->get()]); }
    public function store(Request $request) { EmployeeAdvance::create($this->validated($request)); return redirect()->route('employee-advances.index')->with('success','Employee advance recorded.'); }
    public function edit(EmployeeAdvance $employeeAdvance) { return view('employee-advances.edit', ['advance'=>$employeeAdvance,'employees'=>User::where('role','user')->orderBy('name')->get()]); }
    public function update(Request $request, EmployeeAdvance $employeeAdvance) { $employeeAdvance->update($this->validated($request, $employeeAdvance)); return redirect()->route('employee-advances.index')->with('success','Employee advance updated.'); }
    public function destroy(EmployeeAdvance $employeeAdvance) { $employeeAdvance->delete(); return back()->with('success','Employee advance deleted.'); }
 public function storeReturn(Request $request, EmployeeAdvance $employeeAdvance)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'return_date' => ['required', 'date', 'after_or_equal:'.$employeeAdvance->adv_date->format('Y-m-d')],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($employeeAdvance, $data) {
            $advance = EmployeeAdvance::lockForUpdate()->findOrFail($employeeAdvance->id);
            $returned = (float) $advance->returns()->sum('amount');
            if (round($returned + (float) $data['amount'], 2) > (float) $advance->adv_amount) {
                throw ValidationException::withMessages(['amount' => 'Return amount cannot exceed the outstanding balance.']);
            }
            $advance->returns()->create($data);
        });

        return back()->with('success', 'Employee advance return recorded.');
    }

    public function destroyReturn(EmployeeAdvance $employeeAdvance, EmployeeAdvanceReturn $advanceReturn)
    {
        abort_unless($advanceReturn->employee_advance_id === $employeeAdvance->id, 404);
        $advanceReturn->delete();

        return back()->with('success', 'Employee advance return deleted.');
    }

    private function validated(Request $request, ?EmployeeAdvance $advance = null): array
    {
        $data = $request->validate([
            
        'employee_id'=>['required', Rule::exists('users', 'id')->where('role', 'user')], 'adv_amount'=>['required','numeric','gt:0'], 'adv_date'=>['required','date'],
 ]);

        if ($advance && (float) $data['adv_amount'] < (float) $advance->returns()->sum('amount')) {
            throw ValidationException::withMessages(['adv_amount' => 'Advance amount cannot be less than the amount already returned.']);
        }
        if ($advance && $advance->returns()->whereDate('return_date', '<', $data['adv_date'])->exists()) {
            throw ValidationException::withMessages(['adv_date' => 'Advance date cannot be after an existing return date.']);
        }

        return $data;
    }
}
