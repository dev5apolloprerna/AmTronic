<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeAdvanceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_an_employee_advance_ledger_entry(): void
    {
        $admin=User::factory()->create(['role'=>'super_admin']);
        $employee=User::factory()->create(['role'=>'user']);
        $this->actingAs($admin)->post(route('employee-advances.store'),[
            'employee_id'=>$employee->id,'adv_amount'=>5000,'adv_date'=>'2026-09-01',
        ])->assertRedirect(route('employee-advances.index'))->assertSessionHasNoErrors();
        $advance = \App\Models\EmployeeAdvance::where('employee_id', $employee->id)->firstOrFail();
        $this->post(route('employee-advances.returns.store', $advance), [
            'amount' => 600, 'return_date' => '2026-09-15', 'note' => 'Cash',
        ])->assertSessionHasNoErrors();
        $this->post(route('employee-advances.returns.store', $advance), [
            'amount' => 400, 'return_date' => '2026-09-20', 'note' => 'Salary deduction',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('employee_advances',['employee_id'=>$employee->id,'adv_amount'=>5000]);
        $this->assertDatabaseCount('employee_advance_returns', 2);

        $this->get(route('employee-advances.index',['employee_id'=>$employee->id]))->assertOk()->assertSee('4,000.00');
    }

    public function test_return_cannot_exceed_advance_and_admin_cannot_be_selected_as_employee(): void
    {
        $admin=User::factory()->create(['role'=>'super_admin']);
        $this->actingAs($admin)->post(route('employee-advances.store'),[
         'employee_id'=>$admin->id,'adv_amount'=>100,'adv_date'=>'2026-09-01',
        ])->assertSessionHasErrors(['employee_id']);

        $employee=User::factory()->create(['role'=>'user']);
        $advance=\App\Models\EmployeeAdvance::create(['employee_id'=>$employee->id,'adv_amount'=>100,'adv_date'=>'2026-09-01']);
        $this->post(route('employee-advances.returns.store', $advance), [
            'amount'=>101,'return_date'=>'2026-09-02',
        ])->assertSessionHasErrors(['amount']);

    }
}
