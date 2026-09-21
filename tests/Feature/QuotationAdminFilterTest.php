<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationAdminFilterTest extends TestCase
{
    use RefreshDatabase;

    private function quotation(User $employee, string $number, string $date, string $status = 'draft'): Quotation
    {
        $customer = Customer::create(['name' => "Customer {$number}"]);

        return Quotation::create([
            'quotation_number' => $number,
            'customer_id' => $customer->id,
            'user_id' => $employee->id,
            'quotation_date' => $date,
            'status' => $status,
        ]);
    }

    public function test_admin_can_filter_quotations_by_sales_executive_date_range_and_status(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $ravi = User::factory()->create(['role' => 'user', 'name' => 'Ravi Sales']);
        $meena = User::factory()->create(['role' => 'user', 'name' => 'Meena Sales']);

        $this->quotation($ravi, 'RAVI-IN-RANGE', '2026-09-10', 'approved');
        $this->quotation($ravi, 'RAVI-OUTSIDE', '2026-08-31', 'approved');
        $this->quotation($ravi, 'RAVI-DRAFT', '2026-09-11');
        $this->quotation($meena, 'MEENA-IN-RANGE', '2026-09-12', 'approved');

        $this->actingAs($admin)->get(route('quotations.index', [
            'sales_executive_id' => $ravi->id,
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-30',
            'status' => 'approved',
        ]))
            ->assertOk()
            ->assertSee('RAVI-IN-RANGE')
            ->assertDontSee('RAVI-OUTSIDE')
            ->assertDontSee('RAVI-DRAFT')
            ->assertDontSee('MEENA-IN-RANGE');
    }

    public function test_admin_quotation_list_shows_sales_executive_and_date_filters(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        User::factory()->create(['role' => 'user', 'name' => 'Sales Person']);

        $this->actingAs($admin)->get(route('quotations.index'))
            ->assertOk()
            ->assertSee('Sales Executive')
            ->assertSee('Sales Person')
            ->assertSee('From Date')
            ->assertSee('To Date');
    }

    public function test_date_range_rejects_an_end_date_before_the_start_date(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)->get(route('quotations.index', [
            'date_from' => '2026-09-30',
            'date_to' => '2026-09-01',
        ]))->assertSessionHasErrors('date_to');
    }
}
