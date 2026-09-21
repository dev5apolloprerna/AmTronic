<?php

namespace Tests\Feature;

use App\Models\EmployeeAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin']);
    }

    public function test_admin_sees_all_employees_with_bulk_attendance_controls(): void
    {
        $admin = $this->admin();
        User::factory()->create(['role' => 'user', 'name' => 'Active Employee', 'status' => 'active']);
        User::factory()->create(['role' => 'user', 'name' => 'Inactive Employee', 'status' => 'inactive']);

        $this->actingAs($admin)->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('Active Employee')
            ->assertSee('Inactive Employee')
            ->assertSee('select-all-employees')
            ->assertSee('Present')
            ->assertSee('Absent')
            ->assertSee('Half Day');
    }

    public function test_admin_can_mark_multiple_employees_and_update_existing_attendance(): void
    {
        $admin = $this->admin();
        $first = User::factory()->create(['role' => 'user']);
        $second = User::factory()->create(['role' => 'user']);
        $date = now()->subDay()->toDateString();

        $this->actingAs($admin)->post(route('attendance.store'), [
            'attendance_date' => $date,
            'employee_ids' => [$first->id, $second->id],
            'status' => 'present',
        ])->assertRedirect(route('attendance.index', ['date' => $date]));

        $this->assertDatabaseHas('employee_attendances', [
            'employee_id' => $first->id,
            'attendance_date' => $date,
            'status' => 'present',
            'recorded_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('employee_attendances', [
            'employee_id' => $second->id,
            'attendance_date' => $date,
            'status' => 'present',
        ]);

        $this->actingAs($admin)->post(route('attendance.store'), [
            'attendance_date' => $date,
            'employee_ids' => [$first->id],
            'status' => 'half_day',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('employee_attendances', [
            'employee_id' => $first->id,
            'attendance_date' => $date,
            'status' => 'half_day',
        ]);
        $this->assertSame(2, EmployeeAttendance::count());
    }

    public function test_attendance_requires_an_employee_and_valid_status_and_rejects_admin_ids(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('attendance.store'), [
            'attendance_date' => now()->toDateString(),
            'employee_ids' => [$admin->id],
            'status' => 'late',
        ])->assertSessionHasErrors(['employee_ids.0', 'status']);
    }

    public function test_regular_employee_cannot_access_attendance_management(): void
    {
        $employee = User::factory()->create(['role' => 'user']);

        $this->actingAs($employee)->get(route('attendance.index'))->assertForbidden();
        $this->actingAs($employee)->post(route('attendance.store'), [])->assertForbidden();
    }
}
