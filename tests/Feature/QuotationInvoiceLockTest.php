<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A quotation can be edited or deleted at any status until an invoice is
 * generated from it; after that it is locked.
 */
class QuotationInvoiceLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_sent_and_approved_quotations_without_invoice_can_be_edited_and_deleted(): void
    {
        $user = User::factory()->create();

        foreach ([['draft', 'quotation_sent'], ['approved', 'quotation_sent']] as [$status, $documentStatus]) {
            $quotation = $this->createQuotation($user, $status, $documentStatus);

            $this->assertTrue($quotation->canBeModified());

            $this->actingAs($user)->get(route('quotations.edit', $quotation))->assertOk();
            $this->actingAs($user)->get(route('quotations.show', $quotation))
                ->assertSee(route('quotations.edit', $quotation))
                ->assertSee('action="' . route('quotations.destroy', $quotation) . '"', false);

            $this->actingAs($user)->delete(route('quotations.destroy', $quotation))
                ->assertRedirect(route('quotations.index'))
                ->assertSessionHas('success', 'Quotation deleted successfully.');
            $this->assertDatabaseMissing('quotations', ['id' => $quotation->id]);
        }
    }

    public function test_quotation_with_invoice_cannot_be_edited_or_deleted(): void
    {
        $user = User::factory()->create();
        $quotation = $this->createQuotation($user, 'approved', 'quotation_sent');
        Invoice::create([
            'invoice_number' => 'INV-LOCK-WEB',
            'quotation_id' => $quotation->id,
            'customer_id' => $quotation->customer_id,
            'invoice_date' => now()->toDateString(),
            'sub_total' => 100,
            'total_amount' => 100,
        ]);

        $this->assertFalse($quotation->fresh()->canBeModified());

        $this->actingAs($user)->get(route('quotations.edit', $quotation))
            ->assertRedirect(route('quotations.show', $quotation))
            ->assertSessionHas('error', 'Quotation cannot be edited after its invoice has been generated.');

        $this->actingAs($user)->put(route('quotations.update', $quotation), [])
            ->assertRedirect(route('quotations.show', $quotation))
            ->assertSessionHas('error', 'Quotation cannot be edited after its invoice has been generated.');

        $this->actingAs($user)->delete(route('quotations.destroy', $quotation))
            ->assertSessionHas('error', 'Quotation cannot be deleted after its invoice has been generated.');
        $this->assertDatabaseHas('quotations', ['id' => $quotation->id]);

        $this->actingAs($user)->get(route('quotations.show', $quotation))
            ->assertDontSee(route('quotations.edit', $quotation))
            ->assertDontSee('action="' . route('quotations.destroy', $quotation) . '"', false);
        $this->actingAs($user)->get(route('quotations.index'))
            ->assertDontSee(route('quotations.edit', $quotation))
            ->assertDontSee('action="' . route('quotations.destroy', $quotation) . '"', false);
    }

    private function createQuotation(User $user, string $status, string $documentStatus): Quotation
    {
        $customer = Customer::create(['name' => 'Lock Customer', 'created_by' => $user->id]);
        $product = Product::create(['name' => 'Lock Product', 'unit' => 'Mtr', 'status' => 'active']);
        $quotation = Quotation::create([
            'quotation_number' => 'QUO-LOCK-' . uniqid(),
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'quotation_date' => now()->toDateString(),
            'status' => $status,
            'document_status' => $documentStatus,
            'approved_by' => $status === 'approved' ? $user->id : null,
            'approved_at' => $status === 'approved' ? now() : null,
            'sub_total' => 100,
            'total_amount' => 100,
        ]);
        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'product_id' => $product->id,
            'no_of_rolls' => 1,
            'price_per_mtr' => 100,
            'amount' => 100,
        ]);

        return $quotation;
    }
}
