<?php

namespace Tests\Feature;

use App\Models\Designation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesignationMasterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
    }

    public function test_only_super_admin_can_manage_designations(): void
    {
        $sales = User::factory()->create(['role' => 'user', 'status' => 'active']);

        $this->actingAs($sales)->get(route('designations.index'))->assertForbidden();
        $this->actingAs($sales)->post(route('designations.store'), ['name' => 'Manager', 'status' => 'active'])->assertForbidden();
        $this->actingAs($this->admin())->get(route('designations.index'))->assertOk();
    }

    public function test_admin_can_create_edit_and_search_designations(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('designations.store'), ['name' => 'Sales Executive', 'status' => 'active'])
            ->assertRedirect(route('designations.index'));
        $designation = Designation::where('name', 'Sales Executive')->firstOrFail();

        $this->actingAs($admin)->put(route('designations.update', $designation), ['name' => 'Senior Sales Executive', 'status' => 'inactive'])
            ->assertRedirect(route('designations.index'));
        $this->assertDatabaseHas('designations', ['id' => $designation->id, 'name' => 'Senior Sales Executive', 'status' => 'inactive']);

        $this->actingAs($admin)->get(route('designations.index', ['search' => 'Senior']))
            ->assertOk()->assertSee('Senior Sales Executive');
    }

    public function test_designation_name_must_be_unique_but_can_be_kept_on_edit(): void
    {
        $admin = $this->admin();
        $a = Designation::create(['name' => 'Manager', 'status' => 'active']);
        Designation::create(['name' => 'Driver', 'status' => 'active']);

        $this->actingAs($admin)->post(route('designations.store'), ['name' => 'Manager', 'status' => 'active'])
            ->assertSessionHasErrors('name');
        $this->actingAs($admin)->put(route('designations.update', $a), ['name' => 'Driver', 'status' => 'active'])
            ->assertSessionHasErrors('name');
        $this->actingAs($admin)->put(route('designations.update', $a), ['name' => 'Manager', 'status' => 'inactive'])
            ->assertSessionHasNoErrors();
    }

    public function test_a_designation_assigned_to_employees_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $used = Designation::create(['name' => 'Manager', 'status' => 'active']);
        $unused = Designation::create(['name' => 'Driver', 'status' => 'active']);
        User::factory()->create(['designation_id' => $used->id]);

        $this->actingAs($admin)->delete(route('designations.destroy', $used))->assertSessionHas('error');
        $this->assertDatabaseHas('designations', ['id' => $used->id]);

        $this->actingAs($admin)->delete(route('designations.destroy', $unused))->assertRedirect(route('designations.index'));
        $this->assertDatabaseMissing('designations', ['id' => $unused->id]);
    }

    public function test_designation_screens_render(): void
    {
        $admin = $this->admin();
        $designation = Designation::create(['name' => 'Manager', 'status' => 'active']);

        $this->actingAs($admin)->get(route('designations.create'))->assertOk()->assertSee('Save Designation');
        $this->actingAs($admin)->get(route('designations.edit', $designation))->assertOk()->assertSee('Update Designation');
    }
}
