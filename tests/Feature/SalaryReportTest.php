<?php

namespace Tests\Feature;

use App\Models\EmployeeAttendance;
use App\Models\SalarySlip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalaryReportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
    }

    private function employee(array $attributes = []): User
    {
        return User::factory()->create(['role' => 'user', 'status' => 'active'] + $attributes);
    }

    private function mark(User $employee, array $days): void
    {
        foreach ($days as $date => $status) {
            EmployeeAttendance::create(['employee_id' => $employee->id, 'attendance_date' => $date, 'status' => $status]);
        }
    }

    public function test_two_leaves_are_paid_and_extra_leave_is_cut_per_day(): void
    {
        // September 2026 has 30 days -> 30,000 / 30 = 1,000 per day.
        $employee = $this->employee(['name' => 'Ravi', 'monthly_salary' => 30000]);
        $this->mark($employee, [
            '2026-09-01' => 'present',
            '2026-09-02' => 'absent',
            '2026-09-03' => 'absent',
            '2026-09-04' => 'absent',
            '2026-09-05' => 'half_day',
            '2026-08-31' => 'absent', // other month - ignored
        ]);

        $response = $this->actingAs($this->admin())->get(route('reports.salary', ['month' => 9, 'year' => 2026]));
        $response->assertOk()->assertSee('Ravi')->assertSee('September 2026')->assertSee('Submit Salary');

        $row = $response->viewData('rows')->firstWhere('employee.id', $employee->id);
        $this->assertSame(1, $row->present);
        $this->assertSame(1, $row->half_day);
        $this->assertSame(3, $row->absent);
        $this->assertSame(2.0, $row->paid_leave);
        $this->assertSame(1.5, $row->unpaid_leave);
        $this->assertSame(1500.0, $row->leave_deduction);
        $this->assertSame(28500.0, $row->net_salary);
    }

    public function test_submitting_saves_slips_with_deduction_and_incentive(): void
    {
        $admin = $this->admin();
        $employee = $this->employee(['name' => 'Meena', 'monthly_salary' => 30000]);
        $this->mark($employee, ['2026-09-02' => 'absent', '2026-09-03' => 'absent', '2026-09-04' => 'absent']);

        $this->actingAs($admin)->post(route('reports.salary.store'), [
            'action' => 'submit', 'month' => 9, 'year' => 2026,
            'rows' => [[
                'employee_id' => $employee->id,
                'deduction' => '500',
                'deduction_reason' => 'Advance recovery',
                'incentive' => '2000',
                // a tampered net amount from the browser must be ignored
                'net_salary' => '999999',
            ]],
        ])->assertSessionHasNoErrors()->assertRedirect(route('reports.salary', ['month' => 9, 'year' => 2026]));

        $slip = SalarySlip::where('employee_id', $employee->id)->firstOrFail();
        // 30,000 - 1,000 (1 unpaid day) - 500 + 2,000
        $this->assertSame('30500.00', $slip->net_salary);
        $this->assertSame('1000.00', $slip->leave_deduction);
        $this->assertSame('Advance recovery', $slip->deduction_reason);
        $this->assertSame(3, $slip->absent_days);

        // Re-submitting updates the same slip instead of creating another.
        $this->actingAs($admin)->post(route('reports.salary.store'), [
            'action' => 'submit', 'month' => 9, 'year' => 2026,
            'rows' => [['employee_id' => $employee->id, 'incentive' => '0']],
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, SalarySlip::count());
        $this->assertSame('29000.00', $slip->fresh()->net_salary);

        $this->actingAs($admin)->get(route('reports.salary', ['month' => 9, 'year' => 2026]))
            ->assertSee('Update Submitted Salary')->assertSee(route('salary-slips.show', $slip));
    }

    public function test_deduction_needs_a_reason(): void
    {
        $employee = $this->employee(['monthly_salary' => 10000]);

        $this->actingAs($this->admin())->post(route('reports.salary.store'), [
            'action' => 'submit', 'month' => 9, 'year' => 2026,
            'rows' => [['employee_id' => $employee->id, 'deduction' => '100']],
        ])->assertSessionHasErrors('rows.0.deduction_reason');

        $this->assertSame(0, SalarySlip::count());
    }

    public function test_net_salary_cannot_go_below_zero(): void
    {
        $employee = $this->employee(['monthly_salary' => 1000]);

        $this->actingAs($this->admin())->post(route('reports.salary.store'), [
            'action' => 'submit', 'month' => 9, 'year' => 2026,
            'rows' => [['employee_id' => $employee->id, 'deduction' => '5000', 'deduction_reason' => 'Damage']],
        ])->assertSessionHasErrors('rows.0.deduction');
    }

    public function test_salary_slip_page_and_pdf(): void
    {
        $admin = $this->admin();
        $employee = $this->employee(['name' => 'Slip Person', 'monthly_salary' => 15000]);
        $this->actingAs($admin)->post(route('reports.salary.store'), [
            'action' => 'submit', 'month' => 9, 'year' => 2026,
            'rows' => [['employee_id' => $employee->id, 'incentive' => '750']],
        ])->assertSessionHasNoErrors();
        $slip = SalarySlip::firstOrFail();

        $this->get(route('salary-slips.show', $slip))->assertOk()
            ->assertSee('SEPTEMBER 2026')->assertSee('images/logo-dark.png')->assertSee('Slip Person')->assertSee('15,750.00');

        $pdf = $this->get(route('salary-slips.download', $slip));
        $pdf->assertOk();
        $this->assertStringContainsString('salary-slip-slip-person-2026-09.pdf', $pdf->headers->get('Content-Disposition'));
    }

    public function test_excel_export_includes_adjustments(): void
    {
        $admin = $this->admin();
        $employee = $this->employee(['name' => 'Excel Person', 'monthly_salary' => 15000]);
        $this->actingAs($admin)->post(route('reports.salary.store'), [
            'action' => 'submit', 'month' => 9, 'year' => 2026,
            'rows' => [['employee_id' => $employee->id, 'deduction' => '200', 'deduction_reason' => 'Late fine']],
        ]);

        $response = $this->get(route('reports.salary.excel', ['month' => 9, 'year' => 2026]));
        $response->assertOk()->assertSee('Excel Person')->assertSee('Late fine')->assertSee('14800.00')->assertSee('Submitted');
        $this->assertStringContainsString('salary-2026-09.xls', $response->headers->get('Content-Disposition'));
    }

    public function test_employee_cannot_open_salary_pages(): void
    {
        $employee = $this->employee();
        $this->actingAs($employee)->get(route('reports.salary'))->assertForbidden();
    }

    public function test_employee_form_saves_monthly_salary(): void
    {
        $this->actingAs($this->admin())->post(route('users.store'), [
            'name' => 'Salaried', 'role' => 'user', 'status' => 'active', 'monthly_salary' => '18500',
        ])->assertSessionHasNoErrors();

        $this->assertSame('18500.00', User::where('name', 'Salaried')->first()->monthly_salary);
    }

    public function test_attendance_history_shows_coloured_status(): void
    {
        $employee = $this->employee();
        $this->mark($employee, ['2026-09-01' => 'absent', '2026-09-02' => 'half_day']);

        $this->actingAs($this->admin())->get(route('reports.employee-attendance-history'))
            ->assertOk()->assertSee('pill-attendance-absent')->assertSee('pill-attendance-half_day');
    }

    private function post_salary(User $admin, string $action, array $rows, int $month = 9)
    {
        return $this->actingAs($admin)->post(route('reports.salary.store'), [
            'action' => $action, 'month' => $month, 'year' => 2026, 'rows' => $rows,
        ]);
    }

    public function test_submitted_salary_stays_editable_and_shows_draft_slip(): void
    {
        $admin = $this->admin();
        $employee = $this->employee(['monthly_salary' => 20000]);

        $this->post_salary($admin, 'submit', [['employee_id' => $employee->id, 'incentive' => '100']])->assertSessionHasNoErrors();
        $slip = SalarySlip::firstOrFail();
        $this->assertSame(SalarySlip::SUBMITTED, $slip->status);

        $this->get(route('reports.salary', ['month' => 9, 'year' => 2026]))
            ->assertSee('Submitted &middot; editable', false)->assertSee('Process Salary')->assertSee('Discard submitted salary');
        $this->get(route('salary-slips.show', $slip))->assertSee('DRAFT');

        // a wrong value is simply corrected and submitted again
        $this->post_salary($admin, 'submit', [['employee_id' => $employee->id, 'incentive' => '1000']])->assertSessionHasNoErrors();
        $this->assertSame('1000.00', $slip->fresh()->incentive);
    }

    public function test_submitted_salary_can_be_discarded(): void
    {
        $admin = $this->admin();
        $employee = $this->employee(['monthly_salary' => 20000]);
        $this->post_salary($admin, 'submit', [['employee_id' => $employee->id]]);

        $this->delete(route('reports.salary.destroy'), ['month' => 9, 'year' => 2026])->assertSessionHasNoErrors();
        $this->assertSame(0, SalarySlip::count());
    }

    public function test_processing_locks_the_month(): void
    {
        $admin = $this->admin();
        $employee = $this->employee(['monthly_salary' => 20000]);
        $this->mark($employee, ['2026-09-10' => 'present']);

        $this->post_salary($admin, 'process', [['employee_id' => $employee->id, 'incentive' => '500']])->assertSessionHasNoErrors();
        $slip = SalarySlip::firstOrFail();
        $this->assertTrue($slip->isProcessed());
        $this->assertNotNull($slip->processed_at);
        $this->assertSame($admin->id, $slip->processed_by);

        // no further submit / process
        $this->post_salary($admin, 'submit', [['employee_id' => $employee->id, 'incentive' => '9999']])->assertSessionHasErrors('month');
        $this->assertSame('500.00', $slip->fresh()->incentive);

        // page is read-only, offers delete & regenerate instead
        $this->get(route('reports.salary', ['month' => 9, 'year' => 2026]))
            ->assertSee('Processed &amp; locked', false)->assertDontSee('name="rows[0][incentive]"', false)->assertSee('Delete &amp; Regenerate', false);
        $this->get(route('salary-slips.show', $slip))->assertDontSee('class="draft-mark"', false);
        // the live-recalculation script must not run on a locked sheet
        $this->get(route('reports.salary', ['month' => 9, 'year' => 2026]))->assertDontSee('data-field="deduction"', false)->assertDontSee('function recalc', false);

        // attendance for that month is locked too
        $this->post(route('attendance.store'), ['attendance_date' => '2026-09-10', 'employee_ids' => [$employee->id], 'status' => 'absent'])
            ->assertSessionHas('error');
        $this->assertSame('present', EmployeeAttendance::firstOrFail()->status);

        // later salary changes don't alter the processed figures
        $employee->update(['monthly_salary' => 50000]);
        $row = $this->get(route('reports.salary', ['month' => 9, 'year' => 2026]))->viewData('rows')->first();
        $this->assertSame(20500.0, $row->net_salary);
    }

    public function test_processing_must_include_every_employee(): void
    {
        $admin = $this->admin();
        $a = $this->employee(['monthly_salary' => 1000]);
        $this->employee(['monthly_salary' => 1000]);

        $this->post_salary($admin, 'process', [['employee_id' => $a->id]])->assertSessionHasErrors('rows');
        $this->assertSame(0, SalarySlip::count());
    }

    public function test_processed_salary_needs_confirmation_to_delete_and_can_be_regenerated(): void
    {
        $admin = $this->admin();
        $employee = $this->employee(['monthly_salary' => 20000]);
        $this->post_salary($admin, 'process', [['employee_id' => $employee->id]]);

        $this->delete(route('reports.salary.destroy'), ['month' => 9, 'year' => 2026, 'confirm' => 'no'])
            ->assertSessionHasErrors('confirm');
        $this->assertSame(1, SalarySlip::count());

        $this->delete(route('reports.salary.destroy'), ['month' => 9, 'year' => 2026, 'confirm' => 'delete'])
            ->assertSessionHasNoErrors();
        $this->assertSame(0, SalarySlip::count());

        // month is open again: attendance editable and salary can be regenerated
        $this->post(route('attendance.store'), ['attendance_date' => '2026-09-10', 'employee_ids' => [$employee->id], 'status' => 'absent'])
            ->assertSessionMissing('error');
        $this->post_salary($admin, 'process', [['employee_id' => $employee->id]])->assertSessionHasNoErrors();
    }

    public function test_a_single_submitted_row_can_be_deleted(): void
    {
        $admin = $this->admin();
        $ravi = $this->employee(['name' => 'Ravi', 'monthly_salary' => 20000]);
        $meena = $this->employee(['name' => 'Meena', 'monthly_salary' => 20000]);
        $this->post_salary($admin, 'submit', [
            ['employee_id' => $ravi->id, 'incentive' => '999'],
            ['employee_id' => $meena->id, 'incentive' => '100'],
        ])->assertSessionHasNoErrors();
        $raviSlip = SalarySlip::where('employee_id', $ravi->id)->firstOrFail();

        $this->get(route('reports.salary', ['month' => 9, 'year' => 2026]))->assertSee('delete-slip-'.$raviSlip->id);

        $this->delete(route('salary-slips.destroy', $raviSlip))
            ->assertRedirect(route('reports.salary', ['month' => 9, 'year' => 2026]))->assertSessionHas('success');

        $this->assertNull($raviSlip->fresh());
        $this->assertSame('100.00', SalarySlip::where('employee_id', $meena->id)->value('incentive'));

        $row = $this->get(route('reports.salary', ['month' => 9, 'year' => 2026]))->viewData('rows')->firstWhere('employee.id', $ravi->id);
        $this->assertNull($row->slip);
        $this->assertSame(0.0, $row->incentive);
    }

    public function test_a_processed_row_cannot_be_deleted_on_its_own(): void
    {
        $admin = $this->admin();
        $employee = $this->employee(['monthly_salary' => 20000]);
        $this->post_salary($admin, 'process', [['employee_id' => $employee->id]]);
        $slip = SalarySlip::firstOrFail();

        $this->get(route('reports.salary', ['month' => 9, 'year' => 2026]))->assertDontSee('delete-slip-'.$slip->id);
        $this->delete(route('salary-slips.destroy', $slip))->assertSessionHas('error');
        $this->assertNotNull($slip->fresh());
    }

    public function test_draft_marks_rows_whose_attendance_changed_after_submit(): void
    {
        $admin = $this->admin();
        $employee = $this->employee(['monthly_salary' => 20000]);
        $this->post_salary($admin, 'submit', [['employee_id' => $employee->id]]);
        $this->mark($employee, ['2026-09-03' => 'absent']);

        $row = $this->get(route('reports.salary', ['month' => 9, 'year' => 2026]))->assertSee('Changed since submit')->viewData('rows')->first();
        $this->assertTrue($row->outdated);
    }
}
