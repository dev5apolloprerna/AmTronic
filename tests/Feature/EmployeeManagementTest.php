<?php

namespace Tests\Feature;

use App\Models\Designation;
use App\Models\EmployeeAdvance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ravi Patel',
            'email' => 'ravi@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => 'user',
            'status' => 'active',
        ], $overrides);
    }

    private function advance(User $employee, float $given, float $returned = 0): EmployeeAdvance
    {
        return EmployeeAdvance::create([
            'employee_id' => $employee->id,
            'adv_amount' => $given,
            'adv_date' => '2026-09-01',
            'return_amount' => $returned,
            'return_date' => $returned > 0 ? '2026-09-10' : null,
        ]);
    }

    public function test_list_shows_designation_and_outstanding_advance_balance(): void
    {
        $designation = Designation::create(['name' => 'Sales Executive', 'status' => 'active']);
        $employee = User::factory()->create(['name' => 'Ravi Patel', 'designation_id' => $designation->id]);
        $this->advance($employee, 5000, 1500);
        $this->advance($employee, 3000);

        $html = $this->actingAs($this->admin())->get(route('users.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Sales Executive', $html);
        $this->assertStringContainsString('Advance Balance', $html);
        // 5000 + 3000 given, 1500 returned => 6,500 outstanding
        $this->assertStringContainsString('₹6,500.00', html_entity_decode($html));
        $this->assertStringContainsString('Given ₹8,000.00', html_entity_decode($html));
        $this->assertStringContainsString('Returned ₹1,500.00', html_entity_decode($html));
    }

    public function test_employee_without_advances_shows_zero_and_no_designation_dash(): void
    {
        $admin = $this->admin();

        $html = html_entity_decode($this->actingAs($admin)->get(route('users.index'))->assertOk()->getContent());

        $this->assertStringContainsString('₹0.00', $html);
        $this->assertStringNotContainsString('Given ₹', $html);
    }

    public function test_each_employee_only_shows_their_own_advances(): void
    {
        $a = User::factory()->create(['name' => 'Alpha Emp']);
        $b = User::factory()->create(['name' => 'Beta Emp']);
        $this->advance($a, 1000);
        $this->advance($b, 250);

        $html = html_entity_decode($this->actingAs($this->admin())->get(route('users.index'))->getContent());

        $this->assertStringContainsString('₹1,000.00', $html);
        $this->assertStringContainsString('₹250.00', $html);
    }

    public function test_list_can_be_searched_by_designation_name(): void
    {
        $sales = Designation::create(['name' => 'Sales Executive', 'status' => 'active']);
        $driver = Designation::create(['name' => 'Driver', 'status' => 'active']);
        User::factory()->create(['name' => 'Seller Sam', 'designation_id' => $sales->id]);
        User::factory()->create(['name' => 'Driver Dan', 'designation_id' => $driver->id]);

        $this->actingAs($this->admin())->get(route('users.index', ['search' => 'Sales Exec']))
            ->assertOk()->assertSee('Seller Sam')->assertDontSee('Driver Dan');
    }

    public function test_employee_can_be_created_with_a_designation(): void
    {
        $designation = Designation::create(['name' => 'Sales Executive', 'status' => 'active']);

        $this->actingAs($this->admin())->post(route('users.store'), $this->payload(['designation_id' => $designation->id]))
            ->assertRedirect(route('users.index'))->assertSessionHas('success', 'Employee created successfully.');

        $this->assertDatabaseHas('users', ['email' => 'ravi@example.com', 'designation_id' => $designation->id]);
    }

    public function test_designation_is_optional(): void
    {
        $this->actingAs($this->admin())->post(route('users.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'ravi@example.com', 'designation_id' => null]);
    }

    public function test_unknown_or_inactive_designation_cannot_be_assigned(): void
    {
        $inactive = Designation::create(['name' => 'Retired Role', 'status' => 'inactive']);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('users.store'), $this->payload(['designation_id' => 9999]))
            ->assertSessionHasErrors('designation_id');
        $this->actingAs($admin)->post(route('users.store'), $this->payload(['designation_id' => $inactive->id]))
            ->assertSessionHasErrors('designation_id');
        $this->assertDatabaseMissing('users', ['email' => 'ravi@example.com']);
    }

    public function test_employee_keeps_a_designation_that_was_later_made_inactive(): void
    {
        $designation = Designation::create(['name' => 'Old Role', 'status' => 'active']);
        $employee = User::factory()->create(['designation_id' => $designation->id, 'name' => 'Old Hand']);
        $designation->update(['status' => 'inactive']);
        $admin = $this->admin();

        // Edit form still lists it (marked inactive) so it isn't silently dropped...
        $this->actingAs($admin)->get(route('users.edit', $employee))
            ->assertOk()->assertSee('Old Role (inactive)');

        // ...and saving other changes doesn't trip validation.
        $this->actingAs($admin)->put(route('users.update', $employee), [
            'name' => 'Old Hand Renamed', 'email' => $employee->email, 'role' => 'user',
            'status' => 'active', 'designation_id' => $designation->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame($designation->id, $employee->fresh()->designation_id);
    }

    public function test_designation_can_be_changed_or_cleared_on_edit(): void
    {
        $a = Designation::create(['name' => 'Role A', 'status' => 'active']);
        $b = Designation::create(['name' => 'Role B', 'status' => 'active']);
        $employee = User::factory()->create(['designation_id' => $a->id]);
        $admin = $this->admin();
        $base = ['name' => $employee->name, 'email' => $employee->email, 'role' => 'user', 'status' => 'active'];

        $this->actingAs($admin)->put(route('users.update', $employee), $base + ['designation_id' => $b->id]);
        $this->assertSame($b->id, $employee->fresh()->designation_id);

        $this->actingAs($admin)->put(route('users.update', $employee), $base + ['designation_id' => '']);
        $this->assertNull($employee->fresh()->designation_id);
    }

    public function test_employee_with_advance_records_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $withAdvance = User::factory()->create();
        $plain = User::factory()->create();
        $this->advance($withAdvance, 1000);

        $this->actingAs($admin)->delete(route('users.destroy', $withAdvance))->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $withAdvance->id]);

        $this->actingAs($admin)->delete(route('users.destroy', $plain))->assertRedirect(route('users.index'));
        $this->assertDatabaseMissing('users', ['id' => $plain->id]);
    }

    public function test_employee_create_screen_lists_only_active_designations(): void
    {
        Designation::create(['name' => 'Active Role', 'status' => 'active']);
        Designation::create(['name' => 'Retired Role', 'status' => 'inactive']);

        $this->actingAs($this->admin())->get(route('users.create'))
            ->assertOk()->assertSee('Save Employee')->assertSee('Active Role')->assertDontSee('Retired Role');
    }

    public function test_sidebar_links_to_the_new_masters(): void
    {
        $this->actingAs($this->admin())->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('materials.index'), false)
            ->assertSee(route('designations.index'), false)
            ->assertSee(route('states.index'), false)
            ->assertSee('Employees');
    }
}
