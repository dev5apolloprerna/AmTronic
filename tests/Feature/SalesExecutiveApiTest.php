<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Designation;
use App\Models\NumberSetting;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SalesExecutiveApiTest extends TestCase
{
    use RefreshDatabase;

    private function salesExecutive(): User
    {
        $designation = Designation::firstOrCreate(
            ['name' => 'Sales Executive'],
            ['status' => 'active', 'can_login' => true, 'api_only' => true],
        );

        return User::factory()->create(['designation_id' => $designation->id, 'role' => 'user', 'status' => 'active']);
    }

    private function token(User $user): string
    {
        $response = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
        return $response->json('token');
    }

    private function quotationPayload(): array
    {
        $customer = Customer::create([
            'name' => 'API Customer', 'address' => 'Main Road', 'state' => 'Gujarat',
            'city' => 'Surat', 'pincode' => '395001',
        ]);
        $product = Product::create(['name' => 'Roll', 'code' => 'ROLL-1', 'unit' => 'MTR', 'hsn_code' => '1234', 'status' => 'active']);
        State::create(['name' => 'Gujarat', 'status' => 'active']);
        NumberSetting::create(['document_type' => 'quotation', 'prefix' => 'Q-', 'postfix' => '', 'next_number' => 1, 'number_padding' => 4]);

        return [
            'customer_id' => $customer->id, 'quotation_date' => '2026-09-21', 'gst_applicable' => true,
            'shipping_address' => 'Main Road', 'shipping_state' => 'Gujarat', 'shipping_city' => 'Surat',
            'shipping_pincode' => '395001', 'items' => [[
                'product_id' => $product->id, 'size_mtr' => 10, 'no_of_rolls' => 2, 'price_per_mtr' => 50,
            ]],
        ];
    }

    public function test_sales_executive_can_only_log_in_through_api(): void
    {
        $user = $this->salesExecutive();

        $this->post(route('login.attempt'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()->assertJsonStructure(['token', 'token_type', 'user']);
    }

    public function test_non_login_and_admin_accounts_cannot_use_sales_api_login(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $ordinary = User::factory()->create(['role' => 'user', 'designation_id' => null]);

        $this->postJson('/api/login', ['email' => $admin->email, 'password' => 'password'])->assertForbidden();
        $this->postJson('/api/login', ['email' => $ordinary->email, 'password' => 'password'])->assertForbidden();
    }

    public function test_api_supports_dashboard_password_change_and_logout(): void
    {
        $user = $this->salesExecutive();
        $token = $this->token($user);

        $this->withToken($token)->getJson('/api/dashboard')->assertOk()->assertJsonPath('data.quotation_counts.all', 0);
        $this->withToken($token)->putJson('/api/change-password', [
            'current_password' => 'password', 'password' => 'new-secret', 'password_confirmation' => 'new-secret',
        ])->assertOk();
        $this->assertTrue(Hash::check('new-secret', $user->fresh()->password));
        $this->withToken($token)->postJson('/api/logout')->assertOk();
        $this->withToken($token)->getJson('/api/dashboard')->assertUnauthorized();
    }

    public function test_employee_can_create_list_and_view_only_own_quotations(): void
    {
        $user = $this->salesExecutive();
        $token = $this->token($user);

        $created = $this->withToken($token)->postJson('/api/quotations', $this->quotationPayload())
            ->assertCreated()->assertJsonPath('data.status_code', 'draft');
        $id = $created->json('data.id');

        $this->withToken($token)->getJson('/api/quotations?status=created')->assertOk()
            ->assertJsonPath('data.0.id', $id);
        $this->withToken($token)->getJson("/api/quotations/{$id}")->assertOk()
            ->assertJsonPath('data.editable', true);

        $other = $this->salesExecutive();
        $otherToken = $this->token($other);
        $this->withToken($otherToken)->getJson("/api/quotations/{$id}")->assertForbidden();
    }

    public function test_approved_quotation_cannot_be_edited_in_api_or_admin(): void
    {
        $user = $this->salesExecutive();
        $token = $this->token($user);
        $payload = $this->quotationPayload();
        $id = $this->withToken($token)->postJson('/api/quotations', $payload)->json('data.id');

        $this->withToken($token)->postJson("/api/quotations/{$id}/mark-sent")->assertOk();
        $this->withToken($token)->postJson("/api/quotations/{$id}/approve")->assertOk()
            ->assertJsonPath('data.editable', false);
        $this->withToken($token)->putJson("/api/quotations/{$id}", $payload)->assertStatus(409);

        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $quotation = Quotation::findOrFail($id);
        $this->actingAs($admin)->get(route('quotations.edit', $quotation))
            ->assertRedirect(route('quotations.show', $quotation))
            ->assertSessionHas('error', 'Approved quotations cannot be edited.');
    }
}
