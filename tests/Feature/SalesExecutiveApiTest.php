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
                'product_id' => $product->id, 'description' => 'Product Description', 'qty' => 2, 'rate' => 50,
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

        $this->withToken($token)->postJson('/api/dashboard')->assertOk()->assertJsonPath('data.quotation_counts.all', 0);
        $this->withToken($token)->postJson('/api/change-password', [
            'current_password' => 'password', 'password' => 'new-secret', 'password_confirmation' => 'new-secret',
        ])->assertOk();
        $this->assertTrue(Hash::check('new-secret', $user->fresh()->password));
        $this->withToken($token)->postJson('/api/logout')->assertOk();
        $this->withToken($token)->postJson('/api/dashboard')->assertUnauthorized();
    }

    public function test_employee_can_create_list_and_view_only_own_quotations(): void
    {
        $user = $this->salesExecutive();
        $token = $this->token($user);

        $created = $this->withToken($token)->postJson('/api/quotations', $this->quotationPayload())
            ->assertCreated()->assertJsonPath('data.status_code', 'draft');
        $id = $created->json('data.id');

        $this->withToken($token)->postJson('/api/quotations/list', ['status' => 'created'])->assertOk()
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonMissingPath('current_page')
            ->assertJsonMissingPath('links')
            ->assertJsonMissingPath('per_page');
        $this->withToken($token)->postJson("/api/quotations/{$id}/show")->assertOk()
            ->assertJsonPath('data.editable', true);

        $other = $this->salesExecutive();
        $otherToken = $this->token($other);
        $this->withToken($otherToken)->postJson("/api/quotations/{$id}/show")->assertForbidden();
    }

     public function test_create_endpoint_returns_json_instead_of_redirecting_to_web_login(): void
    {
        $this->postJson('/api/quotations', $this->quotationPayload())
            ->assertUnauthorized()
            ->assertHeader('content-type', 'application/json')
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_employee_can_create_a_quotation_with_multiple_products(): void
    {
        $user = $this->salesExecutive();
        $token = $this->token($user);
        $payload = $this->quotationPayload();
        $secondProduct = Product::create([
            'name' => 'Second Roll', 'code' => 'ROLL-2', 'unit' => 'MTR',
            'hsn_code' => '5678', 'status' => 'active',
        ]);
        $payload['items'][] = [
            'product_id' => $secondProduct->id,
            'description' => 'Second product line',
            'qty' => 3,
            'rate' => 100,
        ];

        $this->withToken($token)->postJson('/api/quotations/create', $payload)
            ->assertCreated()
            ->assertJsonCount(2, 'data.quotation.items')
            ->assertJsonPath('data.quotation.items.0.product_name', 'Roll')
            ->assertJsonPath('data.quotation.items.0.description', 'Standard roll')
            ->assertJsonPath('data.quotation.items.0.qty', 2)
            ->assertJsonPath('data.quotation.items.0.rate', 50)
            ->assertJsonPath('data.quotation.items.0.amount', 100)
            ->assertJsonPath('data.quotation.items.1.product_name', 'Second Roll')
            ->assertJsonPath('data.quotation.items.1.amount', 300)
            ->assertJsonMissingPath('data.quotation.items.0.size_mtr')
            ->assertJsonMissingPath('data.quotation.items.0.despatch_to')
            ->assertJsonMissingPath('data.quotation.items.0.no_of_rolls')
            ->assertJsonMissingPath('data.quotation.items.0.price_per_mtr')
            ->assertJsonPath('data.quotation.sub_total', '400.00')
            ->assertJsonPath('data.total_amount', 472);
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
        $this->withToken($token)->postJson("/api/quotations/{$id}/update", $payload)->assertStatus(409);

        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $quotation = Quotation::findOrFail($id);
        $this->actingAs($admin)->get(route('quotations.edit', $quotation))
            ->assertRedirect(route('quotations.show', $quotation))
            ->assertSessionHas('error', 'Approved quotations cannot be edited.');
    }
    public function test_employee_can_persist_update_and_delete_products_individually(): void
    {
        $user = $this->salesExecutive();
        $token = $this->token($user);
        $payload = $this->quotationPayload();
        $quotationId = $this->withToken($token)->postJson('/api/quotations', $payload)->json('data.id');
        $productId = $payload['items'][0]['product_id'];

        $added = $this->withToken($token)->postJson("/api/quotations/{$quotationId}/items", [
            'product_id' => $productId,
            'description' => 'Warehouse stock',
            'qty' => 3,
            'rate' => 100,
        ])->assertCreated()
            ->assertJsonPath('data.quotation.sub_total', '400.00')
            ->assertJsonPath('data.total_amount', 472);
        $itemId = $added->json('item_id');

        // A fresh request (such as reopening the app) returns the persisted line.
        $this->withToken($token)->postJson("/api/quotations/{$quotationId}/show")
            ->assertOk()
            ->assertJsonPath('data.quotation.items.1.id', $itemId)
            ->assertJsonPath('data.quotation.items.1.amount', 300);

        $this->withToken($token)->postJson("/api/quotations/{$quotationId}/items/{$itemId}/update", [
            'product_id' => $productId,
            'description' => 'Updated stock',
            'qty' => 4,
            'rate' => 100,
        ])->assertOk()
            ->assertJsonPath('data.quotation.sub_total', '500.00')
            ->assertJsonPath('data.total_amount', 590);

        $this->withToken($token)->postJson("/api/quotations/{$quotationId}/items/{$itemId}/delete")
            ->assertOk()
            ->assertJsonCount(1, 'data.quotation.items')
            ->assertJsonPath('data.quotation.sub_total', '100.00')
            ->assertJsonPath('data.total_amount', 118);
    }

    public function test_employee_cannot_change_another_quotation_item_or_items_on_sent_quotation(): void
    {
        $owner = $this->salesExecutive();
        $ownerToken = $this->token($owner);
        $payload = $this->quotationPayload();
        $created = $this->withToken($ownerToken)->postJson('/api/quotations', $payload);
        $quotationId = $created->json('data.id');
        $itemId = $created->json('data.quotation.items.0.id');

        $otherToken = $this->token($this->salesExecutive());
        $this->withToken($otherToken)->postJson("/api/quotations/{$quotationId}/items/{$itemId}/delete")
            ->assertForbidden();

        $this->withToken($ownerToken)->postJson("/api/quotations/{$quotationId}/mark-sent")->assertOk();
        $this->withToken($ownerToken)->postJson("/api/quotations/{$quotationId}/items/{$itemId}/delete")
            ->assertStatus(409);
    }
}
