<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\NumberSetting;
use App\Models\Product;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StateMasterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
    }

    private function customerPayload(string $state): array
    {
        return [
            'name' => 'Acme Traders',
            'address' => '12 Market Road',
            'state' => $state,
            'city' => 'Pune',
            'pincode' => '411001',
            'opening_balance' => 0,
        ];
    }

    public function test_existing_states_are_seeded_by_the_migration(): void
    {
        $this->assertSame(36, State::count());
        $this->assertTrue(State::where('name', 'Gujarat')->where('status', 'active')->exists());
    }

    public function test_only_super_admin_can_open_the_state_master(): void
    {
        $sales = User::factory()->create(['role' => 'user', 'status' => 'active']);

        $this->actingAs($sales)->get(route('states.index'))->assertForbidden();
        $this->actingAs($this->admin())->get(route('states.index'))->assertOk()->assertSee('Maharashtra');
    }

    public function test_admin_can_create_a_state_and_duplicates_are_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('states.store'), ['name' => 'Test Territory', 'status' => 'active'])
            ->assertRedirect(route('states.index'));
        $this->assertDatabaseHas('states', ['name' => 'Test Territory']);

        $this->actingAs($admin)->post(route('states.store'), ['name' => 'Test Territory', 'status' => 'active'])
            ->assertSessionHasErrors('name');
        $this->assertSame(1, State::where('name', 'Test Territory')->count());
    }

    public function test_customer_form_and_validation_use_the_state_master(): void
    {
        $admin = $this->admin();
        State::create(['name' => 'Test Territory', 'status' => 'active']);
        State::where('name', 'Goa')->update(['status' => 'inactive']);

        // Dropdown reflects the master: new state offered, inactive one hidden.
        $this->actingAs($admin)->get(route('customers.create'))
            ->assertOk()->assertSee('Test Territory')->assertDontSee('>Goa<', false);

        // A state added to the master is accepted...
        $this->actingAs($admin)->post(route('customers.store'), $this->customerPayload('Test Territory'))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('customers', ['name' => 'Acme Traders', 'state' => 'Test Territory']);

        // ...while an inactive or unknown one is rejected.
        $this->actingAs($admin)->post(route('customers.store'), $this->customerPayload('Goa'))
            ->assertSessionHasErrors('state');
        $this->actingAs($admin)->post(route('customers.store'), $this->customerPayload('Atlantis'))
            ->assertSessionHasErrors('state');
    }

    public function test_customer_with_a_legacy_state_can_still_be_edited(): void
    {
        $admin = $this->admin();
        $customer = Customer::create(['name' => 'Legacy Co', 'state' => 'Old Name State', 'created_by' => $admin->id]);

        $payload = $this->customerPayload('Old Name State');
        $this->actingAs($admin)->put(route('customers.update', $customer), $payload)->assertSessionHasNoErrors();
        $this->assertSame('Old Name State', $customer->fresh()->state);
    }

    public function test_quotation_shipping_state_is_validated_against_the_master(): void
    {
        $admin = $this->admin();
        NumberSetting::create(['document_type' => 'quotation', 'prefix' => 'QUO-', 'postfix' => '', 'next_number' => 1, 'number_padding' => 4]);
        $customer = Customer::create(['name' => 'Ship Co', 'created_by' => $admin->id]);
        $product = Product::create(['name' => 'Roll', 'unit' => 'Mtr', 'status' => 'active']);
        State::create(['name' => 'Test Territory', 'status' => 'active']);

        $payload = fn (string $state) => [
            'customer_id' => $customer->id,
            'quotation_date' => '2026-09-21',
            'shipping_address' => '1 Dock Road',
            'shipping_state' => $state,
            'shipping_city' => 'Surat',
            'shipping_pincode' => '395001',
            'items' => [['product_id' => $product->id, 'size_mtr' => 10, 'no_of_rolls' => 1, 'price_per_mtr' => 5]],
        ];

        $this->actingAs($admin)->post(route('quotations.store'), $payload('Atlantis'))
            ->assertSessionHasErrors('shipping_state');

        // A state from the master passes validation. (We only assert on validation
        // here: on a database built purely from the repo's migrations the insert that
        // follows fails, because quotations.admin_charges / material_handling_charges
        // have no migration - unrelated to states.)
        $this->actingAs($admin)->post(route('quotations.store'), $payload('Test Territory'))
            ->assertSessionHasNoErrors();
    }

    public function test_an_unused_state_can_be_renamed_deactivated_and_deleted(): void
    {
        $admin = $this->admin();
        $state = State::create(['name' => 'Test Territory', 'status' => 'active']);

        $this->actingAs($admin)->put(route('states.update', $state), ['name' => 'Renamed Territory', 'status' => 'inactive'])
            ->assertRedirect(route('states.index'));
        $this->assertDatabaseHas('states', ['id' => $state->id, 'name' => 'Renamed Territory', 'status' => 'inactive']);

        $this->actingAs($admin)->delete(route('states.destroy', $state))->assertRedirect(route('states.index'));
        $this->assertDatabaseMissing('states', ['id' => $state->id]);
    }

    public function test_a_state_in_use_cannot_be_renamed_deactivated_or_deleted(): void
    {
        $admin = $this->admin();
        $state = State::where('name', 'Maharashtra')->first();
        Customer::create(['name' => 'Pune Co', 'state' => 'Maharashtra', 'created_by' => $admin->id]);

        $this->actingAs($admin)->put(route('states.update', $state), ['name' => 'Maharashtra State', 'status' => 'active'])
            ->assertSessionHas('error');
        $this->actingAs($admin)->put(route('states.update', $state), ['name' => 'Maharashtra', 'status' => 'inactive'])
            ->assertSessionHas('error');
        $this->actingAs($admin)->delete(route('states.destroy', $state))->assertSessionHas('error');

        $this->assertDatabaseHas('states', ['id' => $state->id, 'name' => 'Maharashtra', 'status' => 'active']);
    }

    public function test_the_gst_home_state_is_protected_even_when_unused(): void
    {
        $admin = $this->admin();
        $home = State::where('name', State::HOME_STATE)->first();

        $this->actingAs($admin)->put(route('states.update', $home), ['name' => 'Gujarat', 'status' => 'inactive'])
            ->assertSessionHas('error');
        $this->actingAs($admin)->put(route('states.update', $home), ['name' => 'Gujrat', 'status' => 'active'])
            ->assertSessionHas('error');
        $this->actingAs($admin)->delete(route('states.destroy', $home))->assertSessionHas('error');

        $this->assertDatabaseHas('states', ['name' => 'Gujarat', 'status' => 'active']);
        $this->actingAs($admin)->get(route('states.edit', $home))->assertOk()->assertSee('CGST/SGST');
    }

    public function test_state_screens_render(): void
    {
        $admin = $this->admin();
        $state = State::create(['name' => 'Test Territory', 'status' => 'active']);

        $this->actingAs($admin)->get(route('states.create'))->assertOk()->assertSee('Save State');
        $this->actingAs($admin)->get(route('states.edit', $state))->assertOk()->assertSee('Update State');
    }
}
