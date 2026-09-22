<?php

namespace Tests\Feature;

use App\Models\EmployeeAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeAttendanceReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_counts_each_status_in_the_selected_date_range(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $employee = User::factory()->create(['role' => 'user', 'name' => 'Report Employee']);
        foreach ([['2026-09-01', 'present'], ['2026-09-02', 'absent'], ['2026-09-03', 'half_day'], ['2026-08-01', 'present']] as [$date, $status]) {
            EmployeeAttendance::create(['employee_id' => $employee->id, 'attendance_date' => $date, 'status' => $status]);
        }

        $this->actingAs($admin)->get(route('reports.employee-attendance', [
            'from_date' => '2026-09-01', 'to_date' => '2026-09-30',
        ]))->assertOk()->assertSee('Report Employee')->assertSeeInOrder(['Absent', 'Present', 'Half Day']);

        $response = $this->actingAs($admin)->get(route('reports.employee-attendance', [
            'from_date' => '2026-09-01', 'to_date' => '2026-09-30',
        ]));
        $this->assertSame(1, $response->viewData('employees')->first()->present_count);
        $this->assertSame(1, $response->viewData('employees')->first()->absent_count);
        $this->assertSame(1, $response->viewData('employees')->first()->half_day_count);
    }

    public function test_history_filters_by_employee_and_date_range(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $selected = User::factory()->create(['role' => 'user', 'name' => 'Selected Employee']);
        $other = User::factory()->create(['role' => 'user', 'name' => 'Other Employee']);
        EmployeeAttendance::create(['employee_id' => $selected->id, 'attendance_date' => '2026-09-10', 'status' => 'half_day']);
        EmployeeAttendance::create(['employee_id' => $selected->id, 'attendance_date' => '2026-08-10', 'status' => 'absent']);
        EmployeeAttendance::create(['employee_id' => $other->id, 'attendance_date' => '2026-09-10', 'status' => 'present']);

        $response = $this->actingAs($admin)->get(route('reports.employee-attendance-history', [
            'employee_id' => $selected->id, 'from_date' => '2026-09-01', 'to_date' => '2026-09-30',
        ]));
        $response->assertOk()->assertSee('Selected Employee')->assertSee('Half Day');
        $this->assertCount(1, $response->viewData('history')->items());
    }
}
