<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\DeliveryChallan;
use App\Models\Quotation;
use App\Models\Invoice;
use App\Models\QuotationItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_quotation_charges_are_included_before_gst_and_copied_to_invoice(): void
    {
        $user = User::factory()->create();
        $quotation = $this->createQuotation($user);
        $quotation->update([
            'gst_applicable' => true,
            'discount_amount' => 10,
            'admin_charges' => 20,
            'material_handling_charges' => 30,
        ]);

        $quotation->recalculateTotals();
        $quotation->refresh();

        $this->assertEquals(25.20, $quotation->gst_amount);
        $this->assertEquals(165, $quotation->total_amount);

        $this->actingAs($user)->post(route('quotations.mark-sent', $quotation));
        $this->actingAs($user)->post(route('quotations.generate-invoice', $quotation), [
            'invoice_date' => '2026-09-01',
        ]);

        $invoice = $quotation->fresh()->invoice;
        $this->assertEquals(20, $invoice->admin_charges);
        $this->assertEquals(30, $invoice->material_handling_charges);
        $this->assertEquals(25.20, $invoice->gst_amount);
        $this->assertEquals(165, $invoice->total_amount);
    }

    public function test_invoice_displays_reference_and_requested_item_columns(): void
    {
        $user = User::factory()->create();
        $quotation = $this->createQuotation($user);
        $invoice = Invoice::create([
            'invoice_number' => 'INV-LAYOUT-1',
            'other_reference' => 'CUSTOMER-REF-42',
            'quotation_id' => $quotation->id,
            'customer_id' => $quotation->customer_id,
            'invoice_date' => '2026-08-24',
            'sub_total' => 100,
            'total_amount' => 100,
        ]);
        $invoice->load(['quotation.items.product', 'customer']);

        $html = view('invoices.pdf', compact('invoice'))->render();

        $this->assertStringContainsString('Reference No.', $html);
        $this->assertStringNotContainsString("Supplier's Ref.", $html);
        $this->assertStringContainsString('CUSTOMER-REF-42', $html);
        $this->assertStringContainsString('24 Aug 2026', $html);
        $this->assertStringNotContainsString($quotation->quotation_number, $html);
        $this->assertStringContainsString('Sr. No.', $html);
        $this->assertStringContainsString('Description of Goods', $html);
        $this->assertStringContainsString('No. of Rolls', $html);
        $this->assertStringContainsString('Per Mtr Rate', $html);
        $this->assertStringContainsString('Total Amount', $html);
        $this->assertStringNotContainsString('>Quantity<', $html);
    }


    public function test_sending_approving_and_generating_an_invoice_are_separate_steps(): void
    {
        $user = User::factory()->create();
        $quotation = $this->createQuotation($user);

        $this->actingAs($user)
            ->post(route('quotations.mark-sent', $quotation))
            ->assertRedirect(route('quotations.show', $quotation));

        $quotation->refresh();
        $this->assertTrue($quotation->isSent());
        $this->assertSame('draft', $quotation->status);
        $this->assertNull($quotation->invoice);

        $this->actingAs($user)
            ->post(route('quotations.approve', $quotation))
            ->assertRedirect(route('quotations.show', $quotation));

        $quotation->refresh();
        $this->assertSame('approved', $quotation->status);
        $this->assertNull($quotation->invoice);

        $this->actingAs($user)
            ->post(route('quotations.generate-invoice', $quotation), [
                'invoice_number' => ' INV-MANUAL-42 ',
                'other_reference' => ' PO-REF-17 ',
                'invoice_date' => '2026-08-25',
            ])
            ->assertRedirect(route('quotations.show', $quotation));

        $quotation->refresh();
        $this->assertSame('PO-REF-17', $quotation->invoice->other_reference);
        $this->assertSame('2026-08-25', $quotation->invoice->invoice_date->toDateString());
        $this->assertNull($quotation->invoice->deliveryChallan);
    }

    public function test_a_quotation_cannot_be_approved_before_it_is_sent(): void
    {
        $user = User::factory()->create();
        $quotation = $this->createQuotation($user);

        $this->actingAs($user)
            ->post(route('quotations.approve', $quotation))
            ->assertSessionHas('error', 'Send the quotation before approving it.');

        $this->assertDatabaseMissing('invoices', ['invoice_number' => 'INV-100']);
    }
   public function test_sent_invoice_status_is_shown_on_quotation_pages(): void
    {
        $user = User::factory()->create();
        $quotation = $this->createQuotation($user);
        $invoice = Invoice::create([
            'invoice_number' => 'INV-SENT-1',
            'quotation_id' => $quotation->id,
            'customer_id' => $quotation->customer_id,
            'invoice_date' => now()->toDateString(),
            'sub_total' => 100,
            'total_amount' => 100,
            'document_status' => 'invoice_approved',
        ]);

        $this->actingAs($user)
            ->get(route('quotations.index'))
            ->assertOk()
            ->assertSee('Invoice Sent')
            ->assertSee('pill-invoice-sent');

        $this->actingAs($user)
            ->get(route('quotations.show', $quotation))
            ->assertOk()
            ->assertSee('Invoice Sent')
            ->assertSee('pill-invoice-sent');

        $this->actingAs($user)
            ->get(route('quotations.index', ['status' => 'invoice_sent']))
            ->assertOk()
            ->assertSee($quotation->quotation_number);
    }
    
    public function test_an_unsent_quotation_cannot_generate_an_invoice(): void
    {
        $user = User::factory()->create();
        $quotation = $this->createQuotation($user);

        $this->actingAs($user)
            ->post(route('quotations.generate-invoice', $quotation), ['invoice_number' => 'INV-UNSENT'])
            ->assertSessionHas('error', 'Send the quotation before generating an invoice.');

        $this->assertNull($quotation->fresh()->invoice);
    }

    public function test_sent_quotation_shows_separate_approve_and_generate_invoice_buttons(): void
    {
        $user = User::factory()->create();
        $quotation = $this->createQuotation($user);
        $this->actingAs($user)->post(route('quotations.mark-sent', $quotation));

        $this->actingAs($user)
            ->get(route('quotations.show', $quotation))
            ->assertOk()
            ->assertSee('Approve Quotation')
            ->assertSee('data-modal-open="generateInvoiceModal"', false)
            ->assertSee('name="invoice_number"', false)
            ->assertSee('name="other_reference"', false)
            ->assertSee('name="invoice_date"', false)
            ->assertSee('value="'.now()->toDateString().'"', false);
    }

    public function test_sent_quotation_can_generate_an_invoice_without_approval(): void
    {
        $user = User::factory()->create();
        $quotation = $this->createQuotation($user);
        $this->actingAs($user)->post(route('quotations.mark-sent', $quotation));

        $this->actingAs($user)
            ->post(route('quotations.generate-invoice', $quotation), [
                'invoice_number' => 'INV-WITHOUT-APPROVAL',
                'invoice_date' => '2026-09-02',
            ])
            ->assertRedirect(route('quotations.show', $quotation));

        $quotation->refresh();
        $this->assertTrue($quotation->isSent());
        $this->assertSame('INV-WITHOUT-APPROVAL', $quotation->invoice->invoice_number);


        $this->actingAs($user)
            ->get(route('quotations.show', $quotation))
            ->assertOk()
            ->assertSee('Approve Quotation')
            ->assertSee('Generate Invoice')
            ->assertSee('Approve the quotation first to generate its invoice')
            ->assertSee('View Invoice')
            ->assertSee('Generate Delivery Challan');
    }

    public function test_approved_quotation_shows_separate_generate_invoice_modal_and_download_after_generation(): void
    {
        $user = User::factory()->create();
        $quotation = $this->createQuotation($user);
        $this->actingAs($user)->post(route('quotations.mark-sent', $quotation));

        $this->actingAs($user)
            ->post(route('quotations.approve', $quotation))
            ->assertRedirect(route('quotations.show', $quotation))
            ->assertSessionHas('success', 'Quotation approved successfully. You can now generate the invoice.');

        $quotation->refresh();
        $this->assertSame('approved', $quotation->status);
        $this->assertNull($quotation->invoice);

        $this->actingAs($user)
            ->get(route('quotations.show', $quotation))
            ->assertOk()
            ->assertSee('class="quotation-actions"', false)
            ->assertSee('class="quotation-actions-group quotation-actions-workflow"', false)
            ->assertSee('class="quotation-actions-group quotation-actions-manage"', false)
            ->assertSee('data-modal-open="generateInvoiceModal"', false)
            ->assertSee('name="invoice_number"', false)
            ->assertDontSee('Collect Payment');

        $this->actingAs($user)
            ->post(route('quotations.generate-invoice', $quotation), [
                'invoice_number' => 'INV-APPROVED-1',
                'invoice_date' => '2026-09-03',
            ])
            ->assertRedirect(route('quotations.show', $quotation));

        $quotation->refresh();
        $this->assertNotNull($quotation->invoice);

        $this->actingAs($user)
            ->get(route('quotations.show', $quotation))
            ->assertOk()
            ->assertSee('Download PDF')
            ->assertSee(route('invoices.download', $quotation->invoice), false)
            ->assertSee('Generate Delivery Challan')
            ->assertSee(route('delivery-challans.store', $quotation->invoice), false);
    }

    public function test_an_approved_quotation_shows_the_delivery_challan_action(): void
    {
        $user = User::factory()->create();
        $quotation = $this->createQuotation($user);

        $this->actingAs($user)->post(route('quotations.mark-sent', $quotation));
        $this->actingAs($user)->post(route('quotations.approve', $quotation));
        $this->actingAs($user)->post(route('quotations.generate-invoice', $quotation), [
            'invoice_number' => 'INV-DELIVERY-1',
            'invoice_date' => '2026-09-04',
        ]);

        $quotation->refresh();

        $this->actingAs($user)
            ->get(route('quotations.show', $quotation))
            ->assertOk()
            ->assertSee('Generate Delivery Challan')
            ->assertSee(route('delivery-challans.store', $quotation->invoice), false);

        $challan = DeliveryChallan::create([
            'challan_number' => 'DC-TEST-1',
            'invoice_id' => $quotation->invoice->id,
            'challan_date' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('quotations.show', $quotation))
            ->assertOk()
            ->assertSee('Delivery Challan')
            ->assertSee(route('delivery-challans.show', $challan), false)
            ->assertDontSee('Generate Delivery Challan');
    }
    private function createQuotation(User $user): Quotation
    {
        $customer = Customer::create(['name' => 'Test Customer', 'created_by' => $user->id]);
        $product = Product::create(['name' => 'Test Product', 'unit' => 'Mtr', 'status' => 'active']);
        $quotation = Quotation::create([
            'quotation_number' => 'QUO-TEST-' . uniqid(),
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'quotation_date' => now()->toDateString(),
            'status' => 'draft',
            'document_status' => 'quotation_ready',
            'sub_total' => 100,
            'total_amount' => 100,
        ]);
        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'product_id' => $product->id,
            'size_mtr' => 10,
            'no_of_rolls' => 1,
            'total_mtr' => 10,
            'price_per_mtr' => 10,
            'amount' => 100,
        ]);

        return $quotation;
    }
}
