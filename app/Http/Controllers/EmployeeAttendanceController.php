<?php

namespace App\Http\Controllers;

use App\Models\EmployeeAttendance;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EmployeeAttendanceController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'date' => ['nullable', 'date'],
        ]);
        $date = $data['date'] ?? now()->toDateString();

        $employees = User::with('designation')
            ->where('role', 'user')
            ->orderBy('name')
            ->get();
        $attendance = EmployeeAttendance::whereDate('attendance_date', $date)
            ->get()
            ->keyBy('employee_id');

        return view('attendance.index', compact('employees', 'attendance', 'date'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'attendance_date' => ['required', 'date', 'before_or_equal:today'],
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('users', 'id')->where('role', 'user'),
            ],
            'status' => ['required', Rule::in(EmployeeAttendance::STATUSES)],
        ]);

        DB::transaction(function () use ($request, $data) {
            foreach ($data['employee_ids'] as $employeeId) {
                EmployeeAttendance::updateOrCreate(
                    [
                        'employee_id' => $employeeId,
                        'attendance_date' => $data['attendance_date'],
                    ],
                    [
                        'status' => $data['status'],
                        'recorded_by' => $request->user()->id,
                    ],
                );
            }
        });

        $count = count($data['employee_ids']);
        $status = str_replace('_', ' ', $data['status']);

        return redirect()->route('attendance.index', ['date' => $data['attendance_date']])
            ->with('success', "Attendance marked {$status} for {$count} employee(s).");
    }
}
