<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_vendors_without_a_gst_number(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)->post(route('vendors.store'), [
            'name' => 'Local Supplies',
            'mobile' => '9876543210',
            'gst_number' => '',
        ])->assertRedirect(route('vendors.index'))->assertSessionHasNoErrors();

        $vendor = Vendor::firstOrFail();
        $this->assertNull($vendor->gst_number);

        $this->actingAs($admin)->put(route('vendors.update', $vendor), [
            'name' => 'Local Supplies Pvt Ltd',
            'mobile' => '9876543210',
            'gst_number' => '27ABCDE1234F1Z5',
        ])->assertRedirect(route('vendors.index'));

        $this->assertDatabaseHas('vendors', ['name' => 'Local Supplies Pvt Ltd', 'gst_number' => '27ABCDE1234F1Z5']);
        $this->actingAs($admin)->delete(route('vendors.destroy', $vendor))->assertRedirect(route('vendors.index'));
        $this->assertDatabaseCount('vendors', 0);
    }

    public function test_mobile_is_required_and_gst_must_be_valid_when_supplied(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)->post(route('vendors.store'), [
            'name' => 'Invalid Vendor',
            'gst_number' => 'SHORT',
        ])->assertSessionHasErrors(['mobile', 'gst_number']);
    }

    public function test_employee_cannot_manage_vendors(): void
    {
        $employee = User::factory()->create(['role' => 'user']);
        $this->actingAs($employee)->get(route('vendors.index'))->assertForbidden();
    }
}
