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

        // No longer eager-loading every return row – list page only needs the sum + count
        $advances = EmployeeAdvance::with('employee')
            ->withSum('returns', 'amount')
            ->withCount('returns')
            ->when($employeeId, fn ($q) => $q->where('employee_id', $employeeId))
            ->latest('adv_date')->latest('id')
            ->paginate(25)->withQueryString();

        $employees = User::where('role', 'user')->orderBy('name')->get();
        $advanceQuery = EmployeeAdvance::when($employeeId, fn ($q) => $q->where('employee_id', $employeeId));
        $totals = (object) [
            'advanced' => (clone $advanceQuery)->sum('adv_amount'),
            'returned' => EmployeeAdvanceReturn::whereIn('employee_advance_id', (clone $advanceQuery)->select('id'))->sum('amount'),
        ];

        return view('employee-advances.index', compact('advances', 'employees', 'employeeId', 'totals'));
    }

    // NEW: separate ledger page per advance
    public function ledger(EmployeeAdvance $employeeAdvance)
    {
        $employeeAdvance->load('employee');

        $returns = $employeeAdvance->returns()
            ->orderBy('return_date')->orderBy('id')
            ->get();

        $running = (float) $employeeAdvance->adv_amount;
        foreach ($returns as $return) {
            $running = round($running - (float) $return->amount, 2);
            $return->running_balance = $running;
        }

        $returned = round((float) $returns->sum('amount'), 2);
        $balance  = round((float) $employeeAdvance->adv_amount - $returned, 2);

        return view('employee-advances.ledger', [
            'advance'  => $employeeAdvance,
            'returns'  => $returns,
            'returned' => $returned,
            'balance'  => $balance,
        ]);
    }

    public function create()
    {
        return view('employee-advances.create', ['employees' => User::where('role', 'user')->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $advance = EmployeeAdvance::create($this->validated($request));

        return redirect()->route('employee-advances.ledger', $advance)->with('success', 'Employee advance recorded.');
    }

    public function edit(EmployeeAdvance $employeeAdvance)
    {
        return view('employee-advances.edit', ['advance' => $employeeAdvance, 'employees' => User::where('role', 'user')->orderBy('name')->get()]);
    }

    public function update(Request $request, EmployeeAdvance $employeeAdvance)
    {
        $employeeAdvance->update($this->validated($request, $employeeAdvance));

        return redirect()->route('employee-advances.index')->with('success', 'Employee advance updated.');
    }

    public function destroy(EmployeeAdvance $employeeAdvance)
    {
        DB::transaction(function () use ($employeeAdvance) {
            $employeeAdvance->returns()->delete(); // safe even if FK has cascade
            $employeeAdvance->delete();
        });

        return redirect()->route('employee-advances.index')->with('success', 'Employee advance deleted.');
    }

    public function storeReturn(Request $request, EmployeeAdvance $employeeAdvance)
    {
        $data = $request->validate([
            'amount'      => ['required', 'numeric', 'gt:0'],
            'return_date' => ['required', 'date', 'after_or_equal:'.$employeeAdvance->adv_date->format('Y-m-d')],
            'note'        => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($employeeAdvance, $data) {
            $advance = EmployeeAdvance::lockForUpdate()->findOrFail($employeeAdvance->id);
            $returned = (float) $advance->returns()->sum('amount');
            if (round($returned + (float) $data['amount'], 2) > (float) $advance->adv_amount) {
                throw ValidationException::withMessages(['amount' => 'Return amount cannot exceed the outstanding balance.']);
            }
            $advance->returns()->create($data);
        });

        return redirect()->route('employee-advances.ledger', $employeeAdvance)->with('success', 'Employee advance return recorded.');
    }

    public function destroyReturn(EmployeeAdvance $employeeAdvance, EmployeeAdvanceReturn $advanceReturn)
    {
        abort_unless($advanceReturn->employee_advance_id === $employeeAdvance->id, 404);
        $advanceReturn->delete();

        return redirect()->route('employee-advances.ledger', $employeeAdvance)->with('success', 'Employee advance return deleted.');
    }

    private function validated(Request $request, ?EmployeeAdvance $advance = null): array
    {
        $data = $request->validate([
            'employee_id' => ['required', Rule::exists('users', 'id')->where('role', 'user')],
            'adv_amount'  => ['required', 'numeric', 'gt:0'],
            'adv_date'    => ['required', 'date'],
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