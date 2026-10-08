<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Designation;
use App\Models\NumberSetting;
use App\Models\DeliveryChallan;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

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
        $response = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('status', true);

        return $response->json('token');
    }
    public function test_every_json_api_response_includes_a_boolean_status(): void
    {
        $user = $this->salesExecutive();
        $token = $this->token($user);

        $this->withToken($token)->postJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->postJson('/api/dashboard')
            ->assertUnauthorized()
            ->assertJsonPath('status', false);

        $this->postJson('/api/login', [])
            ->assertUnprocessable()
            ->assertJsonPath('status', false);

        $this->withToken($token)->postJson('/api/route-that-does-not-exist')
            ->assertNotFound()
            ->assertJsonPath('status', false);
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

    public function test_employee_can_view_and_update_profile(): void
    {
        $user = $this->salesExecutive();
        $manager = Designation::create(['name' => 'Manager', 'status' => 'active']);
        Designation::create(['name' => 'Retired Role', 'status' => 'inactive']);

        $token = $this->token($user);

        $this->withToken($token)->postJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.mobile', null)
            ->assertJsonPath('data.designation_id', $user->designation_id)
            ->assertJsonPath('data.designation', 'Sales Executive')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonFragment(['id' => $manager->id, 'name' => 'Manager'])
            ->assertJsonMissing(['name' => 'Retired Role']);

        $this->withToken($token)->postJson('/api/profile/update', [
            'name' => 'Updated Executive',
            'email' => 'updated@example.com',
            'mobile' => '+91 9876543210',
            'designation_id' => $manager->id,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Updated Executive')
            ->assertJsonPath('data.email', 'updated@example.com')
            ->assertJsonPath('data.mobile', '+91 9876543210')
            ->assertJsonPath('data.designation_id', $manager->id)
            ->assertJsonPath('data.designation', 'Manager');



 $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Executive',
            'mobile' => '+91 9876543210',
            'designation_id' => $manager->id,
        ]);
    }

    public function test_profile_update_validates_mobile_and_designation(): void
    {
        $user = $this->salesExecutive();
        $inactive = Designation::create(['name' => 'Inactive', 'status' => 'inactive']);
        $token = $this->token($user);

        $this->withToken($token)->postJson('/api/profile/update', [
            'name' => $user->name,
            'email' => $user->email,
            'mobile' => 'not-a-phone',
            'designation_id' => $inactive->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['mobile', 'designation_id']);

    }

    public function test_forgot_password_otp_can_reset_password_and_revoke_token(): void
    {
        Mail::fake();
        $user = $this->salesExecutive();
        $token = $this->token($user);

        $this->postJson('/api/forgot-password/send-otp', ['email' => $user->email])
            ->assertOk();
        Mail::assertSentCount(1);
        $this->assertDatabaseHas('password_reset_otps', ['email' => $user->email, 'attempts' => 0]);

        DB::table('password_reset_otps')->where('email', $user->email)->update([
            'otp' => Hash::make('123456'),
        ]);

        $this->postJson('/api/forgot-password/reset', [
            'email' => $user->email,
            'otp' => '123456',
            'password' => 'replacement',
            'password_confirmation' => 'replacement',
        ])->assertOk();

        $this->assertTrue(Hash::check('replacement', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_otps', ['email' => $user->email]);
        $this->withToken($token)->postJson('/api/profile')->assertUnauthorized();
    }

    public function test_forgot_password_does_not_reveal_unknown_accounts(): void
    {
        Mail::fake();

        $this->postJson('/api/forgot-password/send-otp', ['email' => 'missing@example.com'])
            ->assertOk()
            ->assertJsonPath('message', 'If the email is eligible, a password reset OTP has been sent.');
        Mail::assertNothingSent();
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
    public function test_quotation_responses_include_document_statuses_and_api_pdf_links(): void
    {
        $user = $this->salesExecutive();
        $token = $this->token($user);
        $quotationId = $this->withToken($token)
            ->postJson('/api/quotations/create', $this->quotationPayload())
            ->json('data.id');
        $quotation = Quotation::findOrFail($quotationId);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-API-1',
            'quotation_id' => $quotation->id,
            'customer_id' => $quotation->customer_id,
            'invoice_date' => '2026-09-22',
            'sub_total' => $quotation->sub_total,
            'gst_amount' => $quotation->gst_amount,
            'total_amount' => $quotation->total_amount,
            'document_status' => 'invoice_approved',
        ]);
        $challan = DeliveryChallan::create([
            'challan_number' => 'DC-API-1',
            'invoice_id' => $invoice->id,
            'challan_date' => '2026-09-23',
        ]);

        $response = $this->withToken($token)->postJson("/api/quotations/{$quotation->id}/show");

        $response->assertOk()
            ->assertJsonPath('data.documents.quotation.status', 'Invoice Sent')
            ->assertJsonPath('data.documents.invoice.status', 'Invoice Sent')
            ->assertJsonPath('data.documents.delivery_challan.status', 'Delivery Challan Ready')
            ->assertJsonStructure(['data' => ['documents' => [
                'quotation' => ['pdf_url'],
                'invoice' => ['pdf_url'],
                'delivery_challan' => ['pdf_url'],
            ]]]);

        foreach ([
            $response->json('data.documents.quotation.pdf_url'),
            $response->json('data.documents.invoice.pdf_url'),
            $response->json('data.documents.delivery_challan.pdf_url'),

        ] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf');
        }
        $tamperedUrl = str_replace('user='.$user->id, 'user='.($user->id + 1), $response->json('data.documents.quotation.pdf_url'));
        $this->get($tamperedUrl)->assertUnauthorized();
    }
     public function test_signed_pdf_links_survive_a_proxy_origin_change(): void
    {
        $user = $this->salesExecutive();
        $token = $this->token($user);
        $quotationId = $this->withToken($token)
            ->postJson('/api/quotations/create', $this->quotationPayload())
            ->json('data.id');
        $quotation = Quotation::findOrFail($quotationId);
        $invoice = Invoice::create([
            'invoice_number' => 'INV-PROXY-1',
            'quotation_id' => $quotation->id,
            'customer_id' => $quotation->customer_id,
            'invoice_date' => '2026-10-01',
            'sub_total' => $quotation->sub_total,
            'gst_amount' => $quotation->gst_amount,
            'total_amount' => $quotation->total_amount,
            'document_status' => 'invoice_ready',
        ]);
        DeliveryChallan::create([
            'challan_number' => 'DC-PROXY-1',
            'invoice_id' => $invoice->id,
            'challan_date' => '2026-10-01',
        ]);

        $documents = $this->withToken($token)
            ->postJson("/api/quotations/{$quotationId}/show")
            ->assertOk()
            ->json('data.documents');

        foreach (['invoice', 'delivery_challan'] as $document) {
            $url = $documents[$document]['pdf_url'];
            $this->assertStringStartsWith('http://localhost/api/', $url);

            // Relative signatures deliberately exclude the origin, so an HTTPS
            // proxy/public host does not invalidate an otherwise genuine URL.
            $proxyUrl = preg_replace('#^http://localhost#', 'https://public.example.test', $url);
            $this->get($proxyUrl)
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf');
        }
    }


    public function test_api_pdf_links_require_the_owner_bearer_token(): void
    {
        $owner = $this->salesExecutive();
        $quotationId = $this->withToken($this->token($owner))
            ->postJson('/api/quotations/create', $this->quotationPayload())
            ->json('data.id');
        $url = route('api.quotations.pdf', $quotationId);

        $this->getJson($url)->assertUnauthorized();
        $this->withToken($this->token($this->salesExecutive()))
            ->getJson($url)
            ->assertForbidden()
            ->assertJsonPath('message', 'You do not have permission to access this document.');
    }
   public function test_employee_can_generate_invoice_and_delivery_challan_like_admin(): void
    {
        $user = $this->salesExecutive();
        $token = $this->token($user);
        $quotationId = $this->withToken($token)
            ->postJson('/api/quotations/create', $this->quotationPayload())
            ->assertCreated()
            ->json('data.id');

        $this->withToken($token)->postJson("/api/quotations/{$quotationId}/mark-sent")->assertOk();

        $invoiceResponse = $this->withToken($token)
            ->postJson("/api/quotations/{$quotationId}/generate-invoice", [
                'invoice_number' => ' INV-API-100 ',
                'invoice_date' => '2026-10-01',
                'other_reference' => ' PO-55 ',
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Invoice generated successfully.')
            ->assertJsonPath('data.documents.invoice.number', 'INV-API-100')
            ->assertJsonPath('data.documents.invoice.status_code', 'invoice_ready')
            ->assertJsonStructure(['data' => ['documents' => ['invoice' => ['id', 'pdf_url']]]]);

        $invoiceId = $invoiceResponse->json('data.documents.invoice.id');
        $quotation = Quotation::findOrFail($quotationId);
        $this->assertDatabaseHas('invoices', [
            'id' => $invoiceId,
            'quotation_id' => $quotationId,
            'invoice_number' => 'INV-API-100',
            'other_reference' => 'PO-55',
            'total_amount' => $quotation->total_amount,
        ]);
        $this->assertDatabaseHas('customer_ledgers', [
            'customer_id' => $quotation->customer_id,
            'reference_type' => 'invoice',
            'reference_id' => $invoiceId,
            'entered_by' => $user->id,
        ]);

        $challanResponse = $this->withToken($token)
            ->postJson("/api/invoices/{$invoiceId}/delivery-challan")
            ->assertCreated()
            ->assertJsonPath('message', 'Delivery challan generated successfully.')
            ->assertJsonPath('data.documents.delivery_challan.status_code', 'delivery_challan_ready')
            ->assertJsonStructure(['data' => ['documents' => ['delivery_challan' => ['id', 'number', 'pdf_url']]]]);

        $this->get($invoiceResponse->json('data.documents.invoice.pdf_url'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->get($challanResponse->json('data.documents.delivery_challan.pdf_url'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->withToken($token)
            ->postJson("/api/quotations/{$quotationId}/generate-invoice", [
                'invoice_number' => 'INV-API-101',
                'invoice_date' => '2026-10-01',
            ])
            ->assertConflict()
            ->assertJsonPath('message', 'Invoice has already been generated.');
        $this->withToken($token)
            ->postJson("/api/invoices/{$invoiceId}/delivery-challan")
            ->assertConflict()
            ->assertJsonPath('message', 'Delivery challan has already been generated.');
    }

    public function test_invoice_generation_api_validates_workflow_and_ownership(): void
    {
        $owner = $this->salesExecutive();
        $ownerToken = $this->token($owner);
        $quotationId = $this->withToken($ownerToken)
            ->postJson('/api/quotations/create', $this->quotationPayload())
            ->json('data.id');

        $this->withToken($ownerToken)
            ->postJson("/api/quotations/{$quotationId}/generate-invoice", [
                'invoice_number' => 'INV-TOO-EARLY',
                'invoice_date' => '2026-10-01',
            ])
            ->assertConflict()
            ->assertJsonPath('message', 'Send the quotation before generating an invoice.');

        $otherToken = $this->token($this->salesExecutive());
        $this->withToken($otherToken)
            ->postJson("/api/quotations/{$quotationId}/generate-invoice", [
                'invoice_number' => 'INV-NOT-OWNED',
                'invoice_date' => '2026-10-01',
            ])
            ->assertForbidden();

        $this->withToken($ownerToken)->postJson("/api/quotations/{$quotationId}/mark-sent")->assertOk();
        $this->withToken($ownerToken)
            ->postJson("/api/quotations/{$quotationId}/generate-invoice", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['invoice_number', 'invoice_date']);
    }
    
    public function test_api_requires_an_explicit_gst_choice(): void
    {
        $token = $this->token($this->salesExecutive());
        $payload = $this->quotationPayload();
        unset($payload['gst_applicable']);

        $this->withToken($token)->postJson('/api/quotations/create', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('gst_applicable');
    }
     public function test_create_quotation_defaults_null_shipping_details_from_customer(): void
    {
        $token = $this->token($this->salesExecutive());
        $payload = $this->quotationPayload();
        $customer = Customer::findOrFail($payload['customer_id']);
        $customer->update(['address_line_2' => 'Warehouse 4']);

        $payload['shipping_address'] = null;
        $payload['shipping_address_line_2'] = null;
        $payload['shipping_state'] = null;
        $payload['shipping_city'] = null;
        $payload['shipping_pincode'] = null;

        $this->withToken($token)->postJson('/api/quotations/create', $payload)
            ->assertCreated()
            ->assertJsonPath('data.quotation.shipping_address', 'Main Road')
            ->assertJsonPath('data.quotation.shipping_address_line_2', 'Warehouse 4')
            ->assertJsonPath('data.quotation.shipping_state', 'Gujarat')
            ->assertJsonPath('data.quotation.shipping_city', 'Surat')
            ->assertJsonPath('data.quotation.shipping_pincode', '395001')
            ->assertJsonPath('data.quotation.shipping_address_different', false);
    }

    public function test_create_quotation_shipping_details_are_optional(): void
    {
        $token = $this->token($this->salesExecutive());
        $payload = $this->quotationPayload();
        $customer = Customer::findOrFail($payload['customer_id']);
        $customer->update([
            'address' => null,
            'state' => null,
            'city' => null,
            'pincode' => null,
        ]);
        unset(
            $payload['shipping_address'],
            $payload['shipping_state'],
            $payload['shipping_city'],
            $payload['shipping_pincode'],
        );

        $this->withToken($token)->postJson('/api/quotations/create', $payload)
            ->assertCreated()
            ->assertJsonPath('data.quotation.shipping_address', null)
            ->assertJsonPath('data.quotation.shipping_state', null)
            ->assertJsonPath('data.quotation.shipping_city', null)
            ->assertJsonPath('data.quotation.shipping_pincode', null);
    }
    


        public function test_dedicated_quotation_lists_return_pending_approved_and_rejected_records(): void
    {
        $user = $this->salesExecutive();
        $token = $this->token($user);
        $payload = $this->quotationPayload();

        $pendingId = $this->withToken($token)->postJson('/api/quotations', $payload)->json('data.id');
        $approvedId = $this->withToken($token)->postJson('/api/quotations', $payload)->json('data.id');
        $rejectedId = $this->withToken($token)->postJson('/api/quotations', $payload)->json('data.id');
        $this->withToken($token)->postJson("/api/quotations/{$approvedId}/mark-sent")->assertOk();
        $this->withToken($token)->postJson("/api/quotations/{$approvedId}/approve")->assertOk();
        $this->withToken($token)->postJson("/api/quotations/{$rejectedId}/reject")->assertOk();

        $this->withToken($token)->postJson('/api/quotations/pending')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $pendingId);
        $this->withToken($token)->postJson('/api/quotations/approved')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $approvedId);
        $this->withToken($token)->postJson('/api/quotations/rejected')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $rejectedId);
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
    public function test_product_can_be_saved_without_a_quotation_master_and_moved_on_final_submit(): void
    {
        $user = $this->salesExecutive();
        $token = $this->token($user);
        $payload = $this->quotationPayload();
        $productId = $payload['items'][0]['product_id'];

        $saved = $this->withToken($token)->postJson('/api/quotations/items', [
            'quotation_id' => 0,
            'product_id' => $productId,
            'description' => 'Saved before quotation',
            'qty' => 2.5,
            'rate' => 80,
        ])->assertCreated()
            ->assertJsonPath('quotation_id', 0)
            ->assertJsonPath('data.id', 0)
            ->assertJsonPath('data.quotation.id', 0)
            ->assertJsonPath('data.quotation.items.0.quotation_id', 0)
            ->assertJsonPath('data.quotation.items.0.amount', 200)
            ->assertJsonCount(1, 'data.quotation.items')
            ->assertJsonPath('data.total_amount', 200);
        $this->assertSame($saved->json('data.quotation.items.0.id'), $saved->json('item_id'));

        $this->withToken($token)->postJson('/api/quotations/items', [
            'quotation_id' => 0,
            'product_id' => $productId,
            'description' => 'Second saved product',
            'qty' => 1,
            'rate' => 20,
        ])->assertCreated()
        ->assertJsonCount(2, 'data.quotation.items')
        ->assertJsonPath('data.total_amount', 220);
        $this->assertDatabaseCount('quotations', 0);
        $this->assertDatabaseCount('quotation_items', 2);
        $this->assertDatabaseHas('quotation_items', [
            'quotation_id' => 0,
            'user_id' => $user->id,
            'product_id' => $productId,
        ]);


        unset($payload['items']);
        $payload['quotation_id'] = 0;
        $created = $this->withToken($token)->postJson('/api/quotations/create', $payload)
            ->assertCreated()
            ->assertJsonPath('data.id', 1)
            ->assertJsonPath('data.quotation.is_temporary', false)
            ->assertJsonPath('data.quotation.items.0.description', 'Saved before quotation')
            ->assertJsonPath('data.quotation.items.0.amount', 200);

        $this->assertDatabaseHas('quotation_items', [
            'quotation_id' => $created->json('data.id'),
            'product_id' => $productId,
            'amount' => 200,
        ]);
        $this->assertDatabaseHas('quotations', [
            'id' => $created->json('data.id'),
            'user_id' => $user->id,
            'is_temporary' => false,
        ]);
         $this->assertDatabaseMissing('quotation_items', [
            'quotation_id' => 0,
            'user_id' => $user->id,
        ]);
    }

    public function test_draft_products_are_isolated_between_employees(): void
    {
        $owner = $this->salesExecutive();
        $ownerToken = $this->token($owner);
        $payload = $this->quotationPayload();
        $productId = $payload['items'][0]['product_id'];
        $saved = $this->withToken($ownerToken)->postJson('/api/quotations/items', [
            'quotation_id' => 0,
            'product_id' => $productId,
            'qty' => 1,
            'rate' => 50,
        ])->assertCreated();

        $otherToken = $this->token($this->salesExecutive());
        unset($payload['items']);
        $payload['quotation_id'] = 0;
        $this->withToken($otherToken)->postJson('/api/quotations/create', $payload)
->assertUnprocessable()
            ->assertJsonValidationErrors('items');

        $this->withToken($otherToken)->postJson('/api/quotations/items/update', [
            'quotation_id' => 0,
            'item_id' => $saved->json('item_id'),
            'product_id' => $productId,
            'qty' => 2,
            'rate' => 75,
        ])->assertNotFound();
    }

    public function test_employee_can_edit_and_delete_draft_products_with_zero_quotation_id(): void
    {
        $user = $this->salesExecutive();
        $token = $this->token($user);
        $payload = $this->quotationPayload();
        $productId = $payload['items'][0]['product_id'];

        $itemId = $this->withToken($token)->postJson('/api/quotations/items', [
            'quotation_id' => 0,
           'product_id' => $productId,
            'qty' => 1,
            'rate' => 50,
        ])->assertCreated()->json('item_id');

        $this->withToken($token)->postJson('/api/quotations/items/update', [
            'quotation_id' => 0,
            'item_id' => $itemId,
            'product_id' => $productId,
            'description' => 'Updated before submit',
            'qty' => 3,
            'rate' => 75,
        ])->assertOk()
            ->assertJsonPath('quotation_id', 0)
            ->assertJsonPath('data.quotation.items.0.quotation_id', 0)
            ->assertJsonPath('data.quotation.items.0.amount', 225);

        $this->withToken($token)->postJson('/api/quotations/items/delete', [
            'quotation_id' => 0,
            'item_id' => $itemId,
        ])->assertOk()
            ->assertJsonPath('quotation_id', 0)
            ->assertJsonCount(0, 'data.quotation.items');
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
            ->assertSessionHas('error', 'Sent, approved, or invoiced quotations cannot be edited.');
    }

    public function test_employee_can_update_quotation_details_without_sending_items(): void
    {
        $user = $this->salesExecutive();
        $token = $this->token($user);
        $payload = $this->quotationPayload();
        $productId = $payload['items'][0]['product_id'];
        Customer::findOrFail($payload['customer_id'])->update([
            'address' => 'Customer Default Road',
            'address_line_2' => 'Customer Default Building',
            'state' => 'Gujarat',
            'city' => 'Vadodara',
            'pincode' => '390001',
        ]);
        $quotationId = $this->withToken($token)
            ->postJson('/api/quotations/create', $payload)
            ->assertCreated()
            ->json('data.id');

        unset($payload['items']);
        $payload['shipping_address'] = null;
        $payload['shipping_address_line_2'] = null;
        $payload['shipping_state'] = null;
        $payload['shipping_city'] = null;
        $payload['shipping_pincode'] = null;

        $this->withToken($token)
            ->postJson("/api/quotations/{$quotationId}/update", $payload)
            ->assertOk()
            ->assertJsonPath('message', 'Quotation updated successfully.')
            ->assertJsonPath('data.quotation.shipping_address', 'Customer Default Road')
            ->assertJsonPath('data.quotation.shipping_address_line_2', 'Customer Default Building')
            ->assertJsonPath('data.quotation.shipping_state', 'Gujarat')
            ->assertJsonPath('data.quotation.shipping_city', 'Vadodara')
            ->assertJsonPath('data.quotation.shipping_pincode', '390001')
            ->assertJsonCount(1, 'data.quotation.items')
            ->assertJsonPath('data.quotation.items.0.amount', 100);

        $this->assertDatabaseHas('quotation_items', [
            'quotation_id' => $quotationId,
            'product_id' => $productId,
            'amount' => 100,
        ]);
    }

    public function test_employee_can_persist_update_and_delete_products_individually(): void
    {
        $user = $this->salesExecutive();
        $token = $this->token($user);
        $payload = $this->quotationPayload();
        $quotationId = $this->withToken($token)->postJson('/api/quotations', $payload)->json('data.id');
        $productId = $payload['items'][0]['product_id'];

        $added = $this->withToken($token)->postJson('/api/quotations/items', [
            'quotation_id' => $quotationId,
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

        $this->withToken($token)->postJson('/api/quotations/items/update', [
            'quotation_id' => $quotationId,
            'item_id' => $itemId,
            'product_id' => $productId,
            'description' => 'Updated stock',
            'qty' => 4,
            'rate' => 100,
        ])->assertOk()
            ->assertJsonPath('quotation_id', $quotationId)
            ->assertJsonPath('data.quotation.items.1.quotation_id', $quotationId)
            ->assertJsonPath('data.quotation.sub_total', '500.00')
            ->assertJsonPath('data.total_amount', 590);

        $this->withToken($token)->postJson('/api/quotations/items/delete', [
            'quotation_id' => $quotationId,
            'item_id' => $itemId,
        ])
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
        $this->withToken($otherToken)->postJson('/api/quotations/items/delete', [
            'quotation_id' => $quotationId,
            'item_id' => $itemId,
        ])
            ->assertForbidden();

        $this->withToken($ownerToken)->postJson("/api/quotations/{$quotationId}/mark-sent")->assertOk();
        $this->withToken($ownerToken)->postJson('/api/quotations/items/delete', [
            'quotation_id' => $quotationId,
            'item_id' => $itemId,
        ])
            ->assertStatus(409);
    }
     public function test_item_delete_validation_returns_json_without_an_accept_header(): void
    {
        $token = $this->token($this->salesExecutive());

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/quotations/items/delete', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quotation_id', 'item_id']);
    }

}
