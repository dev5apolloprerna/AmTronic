<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DeliveryChallan;
use App\Models\Invoice;
use App\Models\Material;
use App\Models\NumberSetting;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A quotation line is an item (a Product OR a Material), a description, a
 * quantity and a rate - and the quotation / invoice / challan PDFs show them.
 */
class QuotationItemsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Customer $customer;
    private Product $product;
    private Material $material;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        NumberSetting::create(['document_type' => 'quotation', 'prefix' => 'QUO-', 'postfix' => '', 'next_number' => 1, 'number_padding' => 4]);
        $this->customer = Customer::create(['name' => 'Ship Co', 'state' => 'Gujarat', 'created_by' => $this->admin->id]);
        $this->product = Product::create(['name' => 'Rubber Roll', 'code' => 'P-1', 'unit' => 'Mtr', 'hsn_code' => '401110', 'description' => 'Master product text', 'status' => 'active']);
        $this->material = Material::create(['name' => 'PVC Granules', 'code' => 'M-1', 'unit' => 'Kg', 'hsn_code' => '3904', 'description' => 'Master material text', 'status' => 'active']);
    }

    // ------------------------------------------------------------------ helpers

    private function payload(array $items, array $overrides = []): array
    {
        return array_merge([
            'customer_id' => $this->customer->id,
            'quotation_date' => '2026-09-21',
            'shipping_address' => '1 Dock Road',
            'shipping_state' => 'Gujarat',
            'shipping_city' => 'Surat',
            'shipping_pincode' => '395001',
            'items' => $items,
        ], $overrides);
    }

    private function line(string $item, $qty = 2, $rate = 100, ?string $description = null): array
    {
        return ['item' => $item, 'qty' => $qty, 'rate' => $rate, 'description' => $description];
    }

    private function productKey(): string
    {
        return 'product:' . $this->product->id;
    }

    private function materialKey(): string
    {
        return 'material:' . $this->material->id;
    }

    private function storeQuotation(array $items, array $overrides = []): Quotation
    {
        $this->actingAs($this->admin)->post(route('quotations.store'), $this->payload($items, $overrides))
            ->assertSessionHasNoErrors();

        return Quotation::latest('id')->firstOrFail();
    }

    /** A quotation with a product line and a material line, already sent and invoiced. */
    private function invoicedQuotation(): array
    {
        $quotation = $this->storeQuotation([
            $this->line($this->productKey(), 2.5, 100, 'Grey, 2 mm'),
            $this->line($this->materialKey(), 4, 50, 'Bag of 25 kg'),
        ]);

        $this->actingAs($this->admin)->post(route('quotations.mark-sent', $quotation))->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('quotations.generate-invoice', $quotation), [
            'invoice_number' => 'INV-T-1', 'invoice_date' => '2026-09-21',
        ])->assertSessionHasNoErrors();

        $invoice = Invoice::where('quotation_id', $quotation->id)->firstOrFail();
        $this->actingAs($this->admin)->post(route('delivery-challans.store', $invoice));

        return [$quotation->fresh(), $invoice, DeliveryChallan::where('invoice_id', $invoice->id)->firstOrFail()];
    }

    // ------------------------------------------------------------------ the form

    public function test_item_dropdown_lists_products_and_materials_with_description_qty_and_rate(): void
    {
        Product::create(['name' => 'Hidden Inactive Product', 'unit' => 'Nos', 'hsn_code' => '8536', 'status' => 'inactive']);
        Material::create(['name' => 'Hidden Inactive Material', 'unit' => 'Nos', 'hsn_code' => '8536', 'status' => 'inactive']);

        $html = $this->actingAs($this->admin)->get(route('quotations.create'))->assertOk()->getContent();

        $this->assertStringContainsString('<optgroup label="Products">', $html);
        $this->assertStringContainsString('<optgroup label="Materials">', $html);
        $this->assertStringContainsString('value="product:' . $this->product->id . '"', $html);
        $this->assertStringContainsString('value="material:' . $this->material->id . '"', $html);
        $this->assertStringContainsString('name="items[__INDEX__][description]"', $html);
        $this->assertStringContainsString('name="items[__INDEX__][qty]"', $html);
        $this->assertStringContainsString('name="items[__INDEX__][rate]"', $html);
        $this->assertStringNotContainsString('Hidden Inactive Product', $html);
        $this->assertStringNotContainsString('Hidden Inactive Material', $html);
        // the roll-based inputs are gone
        $this->assertStringNotContainsString('Size (Mtr)', $html);
        $this->assertStringNotContainsString('# of Rolls', $html);
        $this->assertStringNotContainsString('js-rolls', $html);
    }

    public function test_master_descriptions_are_offered_to_prefill_the_description_box(): void
    {
        $html = $this->actingAs($this->admin)->get(route('quotations.create'))->getContent();

        $this->assertStringContainsString('data-description="Master product text"', $html);
        $this->assertStringContainsString('data-description="Master material text"', $html);
    }

    // ------------------------------------------------------------------ saving

    public function test_a_product_line_is_saved_with_description_qty_rate_and_amount(): void
    {
        $quotation = $this->storeQuotation([$this->line($this->productKey(), 3, 250.50, 'Black, 5 mm')]);

        $item = $quotation->items()->firstOrFail();
        $this->assertSame($this->product->id, $item->product_id);
        $this->assertNull($item->material_id);
        $this->assertSame('Black, 5 mm', $item->description);
        $this->assertEquals(3, $item->qty);
        $this->assertEquals(250.50, $item->rate);
        $this->assertEquals(751.50, $item->amount);
        // nothing roll-based is collected any more
        $this->assertNull($item->size_mtr);
        $this->assertNull($item->total_mtr);
        $this->assertEquals(751.50, $quotation->fresh()->sub_total);
    }

    public function test_a_material_line_is_saved(): void
    {
        $quotation = $this->storeQuotation([$this->line($this->materialKey(), 10, 42)]);

        $item = $quotation->items()->firstOrFail();
        $this->assertSame($this->material->id, $item->material_id);
        $this->assertNull($item->product_id);
        $this->assertEquals(420, $item->amount);
        $this->assertSame('PVC Granules', $item->item_name);
        $this->assertSame('3904', $item->item_hsn);
        $this->assertSame('Kg', $item->item_unit);
    }

    public function test_products_and_materials_can_be_mixed_and_totals_add_up(): void
    {
        $quotation = $this->storeQuotation([
            $this->line($this->productKey(), 2, 100),
            $this->line($this->materialKey(), 5, 30),
        ], ['gst_applicable' => 1, 'discount_amount' => 50]);

        $this->assertSame(2, $quotation->items()->count());
        $quotation->refresh();
        $this->assertEquals(350, $quotation->sub_total);          // 200 + 150
        $this->assertEquals(54, $quotation->gst_amount);          // (350 - 50) * 18%
        $this->assertEquals(354, $quotation->total_amount);
    }

    public function test_quantity_may_be_fractional_and_is_not_truncated(): void
    {
        $quotation = $this->storeQuotation([$this->line($this->productKey(), 12.5, 10.10)]);

        $item = $quotation->items()->firstOrFail();
        $this->assertEquals(12.5, (float) $item->getRawOriginal('no_of_rolls'), 'the stored value keeps its decimals');
        $this->assertEquals(12.5, $item->qty);
        $this->assertSame('12.5', $item->qty_label);
        $this->assertEquals(126.25, $item->amount);
    }

    public function test_description_is_optional_and_blank_is_stored_as_null(): void
    {
        $quotation = $this->storeQuotation([$this->line($this->productKey(), 1, 10, '   ')]);

        $this->assertNull($quotation->items()->firstOrFail()->description);
    }

    // ------------------------------------------------------------------ validation

    #[DataProvider('badItems')]
    public function test_invalid_item_values_are_rejected(string $field, mixed $value): void
    {
        $line = $this->line($this->productKey());
        $line[$field] = $value;

        $this->actingAs($this->admin)->post(route('quotations.store'), $this->payload([$line]))
            ->assertSessionHasErrors('items.0.' . $field);

        $this->assertSame(0, Quotation::count());
    }

    public static function badItems(): array
    {
        return [
            'no item chosen' => ['item', ''],
            'malformed key' => ['item', 'product:abc'],
            'unknown type' => ['item', 'service:1'],
            'leading zero' => ['item', 'product:01'],
            'product that does not exist' => ['item', 'product:99999'],
            'material that does not exist' => ['item', 'material:99999'],
            'zero quantity' => ['qty', 0],
            'negative quantity' => ['qty', -1],
            'absurd quantity' => ['qty', 1000000],
            'no quantity' => ['qty', ''],
            'text quantity' => ['qty', 'lots'],
            'negative rate' => ['rate', -5],
            'absurd rate' => ['rate', 10000000],
            'no rate' => ['rate', ''],
            'description too long' => ['description', str_repeat('x', 1001)],
        ];
    }

    public function test_a_quotation_needs_at_least_one_line(): void
    {
        $this->actingAs($this->admin)->post(route('quotations.store'), $this->payload([]))
            ->assertSessionHasErrors('items');
    }

    public function test_an_inactive_product_or_material_cannot_be_added(): void
    {
        $this->product->update(['status' => 'inactive']);
        $this->material->update(['status' => 'inactive']);
        $admin = $this->actingAs($this->admin);

        $admin->post(route('quotations.store'), $this->payload([$this->line($this->productKey())]))
            ->assertSessionHasErrors('items.0.item');
        $admin->post(route('quotations.store'), $this->payload([$this->line($this->materialKey())]))
            ->assertSessionHasErrors('items.0.item');
        $this->assertSame(0, Quotation::count());
    }

    // ------------------------------------------------------------------ editing

    public function test_edit_form_preselects_the_saved_item_and_keeps_its_description(): void
    {
        $quotation = $this->storeQuotation([$this->line($this->materialKey(), 4, 25, 'Bag of 25 kg')]);

        $html = $this->actingAs($this->admin)->get(route('quotations.edit', $quotation))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/value="material:' . $this->material->id . '"[^>]*selected/', $html);
        $this->assertDoesNotMatchRegularExpression('/value="product:' . $this->product->id . '"[^>]*selected/', $html);
        $this->assertStringContainsString('Bag of 25 kg', $html);
        $this->assertStringContainsString('value="4"', $html);
        $this->assertStringContainsString('value="25.00"', $html);
    }

    public function test_update_replaces_the_lines_and_recalculates_totals(): void
    {
        $quotation = $this->storeQuotation([$this->line($this->productKey(), 1, 100)]);

        $this->actingAs($this->admin)->put(route('quotations.update', $quotation), $this->payload([
            $this->line($this->materialKey(), 3, 20, 'Changed'),
        ]))->assertSessionHasNoErrors();

        $items = $quotation->fresh()->items;
        $this->assertCount(1, $items);
        $this->assertSame($this->material->id, $items[0]->material_id);
        $this->assertNull($items[0]->product_id);
        $this->assertEquals(60, $quotation->fresh()->sub_total);
    }

    public function test_an_item_that_was_deactivated_later_stays_on_the_quotation_when_editing(): void
    {
        $quotation = $this->storeQuotation([$this->line($this->productKey(), 1, 100)]);
        $this->product->update(['status' => 'inactive']);

        $html = $this->actingAs($this->admin)->get(route('quotations.edit', $quotation))->getContent();
        $this->assertMatchesRegularExpression('/value="product:' . $this->product->id . '"[^>]*selected/', $html);
        $this->assertStringContainsString('- inactive', $html);

        $this->actingAs($this->admin)->put(route('quotations.update', $quotation), $this->payload([
            $this->line($this->productKey(), 5, 100),
        ]))->assertSessionHasNoErrors();
        $this->assertEquals(5, $quotation->fresh()->items()->first()->qty);
    }

    // ------------------------------------------------------------------ quotations made before the change

    private function legacyQuotation(): Quotation
    {
        $quotation = Quotation::create([
            'quotation_number' => 'QUO-OLD-1', 'customer_id' => $this->customer->id, 'user_id' => $this->admin->id,
            'quotation_date' => '2026-08-01', 'status' => 'draft', 'document_status' => 'quotation_ready',
            'shipping_address' => '1 Dock Road', 'shipping_state' => 'Gujarat', 'shipping_city' => 'Surat', 'shipping_pincode' => '395001',
            'sub_total' => 150, 'total_amount' => 150,
        ]);
        // exactly how a line was stored before: size x rolls, no description, no material
        QuotationItem::create([
            'quotation_id' => $quotation->id, 'product_id' => $this->product->id,
            'size_mtr' => 10, 'no_of_rolls' => 3, 'total_mtr' => 30, 'price_per_mtr' => 50, 'amount' => 150,
        ]);

        return $quotation;
    }

    public function test_an_old_roll_based_quotation_still_shows_its_size_rolls_and_the_products_description(): void
    {
        $quotation = $this->legacyQuotation();

        $html = html_entity_decode($this->actingAs($this->admin)->get(route('quotations.show', $quotation))->assertOk()->getContent());

        $this->assertStringContainsString('Rubber Roll', $html);
        $this->assertStringContainsString('Size: 10.00 Mtr', $html);
        $this->assertStringContainsString('3 Rolls', $html);
        $this->assertStringContainsString('Master product text', $html);   // falls back to the product's description
        $this->assertStringContainsString('₹50.00', $html);
        $this->assertStringContainsString('₹150.00', $html);
    }

    public function test_editing_an_old_draft_keeps_its_roll_size(): void
    {
        $quotation = $this->legacyQuotation();

        $html = $this->actingAs($this->admin)->get(route('quotations.edit', $quotation))->getContent();
        $this->assertStringContainsString('name="items[0][size_mtr]"', $html);

        $this->actingAs($this->admin)->put(route('quotations.update', $quotation), $this->payload([
            ['item' => $this->productKey(), 'qty' => 4, 'rate' => 50, 'description' => null, 'size_mtr' => '10.00'],
        ]))->assertSessionHasNoErrors();

        $item = $quotation->fresh()->items()->firstOrFail();
        $this->assertEquals(10, $item->size_mtr);
        $this->assertEquals(40, $item->total_mtr);      // 10 Mtr x 4 rolls
        $this->assertEquals(200, $item->amount);
        $this->assertSame('4 Rolls', $item->qty_with_unit);
    }

    // ------------------------------------------------------------------ the three PDFs

    public function test_quotation_invoice_and_challan_pdfs_are_generated_for_product_and_material_lines(): void
    {
        [$quotation, $invoice, $challan] = $this->invoicedQuotation();
        $admin = $this->actingAs($this->admin);

        foreach ([
            route('quotations.download', $quotation),
            route('invoices.download', $invoice),
            route('delivery-challans.download', $challan),
        ] as $url) {
            $response = $admin->get($url)->assertOk();
            $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'), $url);
            $this->assertStringStartsWith('%PDF', $response->getContent(), $url);
        }
    }

    public function test_quotation_pdf_shows_name_hsn_description_quantity_rate_and_amount(): void
    {
        [$quotation] = $this->invoicedQuotation();
        $quotation->load('items.product', 'items.material', 'customer', 'user');

        $html = html_entity_decode(view('quotations.pdf', compact('quotation'))->render());

        $this->assertStringContainsString('Description of Goods', $html);
        $this->assertStringContainsString('Rubber Roll', $html);
        $this->assertStringContainsString('401110', $html);
        $this->assertStringContainsString('Grey, 2 mm', $html);          // the line's own description...
        $this->assertStringNotContainsString('Master product text', $html); // ...wins over the master's
        $this->assertStringContainsString('2.5 Mtr', $html);
        $this->assertStringContainsString('250.00', $html);
        $this->assertStringContainsString('PVC Granules', $html);
        $this->assertStringContainsString('3904', $html);
        $this->assertStringContainsString('Bag of 25 kg', $html);
        $this->assertStringContainsString('4 Kg', $html);
        $this->assertStringContainsString('200.00', $html);
        $this->assertStringNotContainsString('Rolls', $html);
        // Mtr + Kg cannot be added up, so there is no total-quantity row
        $this->assertStringNotContainsString('Total Qty', $html);
    }

    public function test_quotation_pdf_totals_the_quantity_only_when_every_line_shares_a_unit(): void
    {
        $other = Product::create(['name' => 'Cable', 'unit' => 'Mtr', 'hsn_code' => '8544', 'status' => 'active']);
        $quotation = $this->storeQuotation([
            $this->line($this->productKey(), 2.5, 100),
            $this->line('product:' . $other->id, 4, 10),
        ]);
        $quotation->load('items.product', 'items.material', 'customer', 'user');

        $html = view('quotations.pdf', compact('quotation'))->render();

        $this->assertStringContainsString('Total Qty', $html);
        $this->assertStringContainsString('6.5 Mtr', $html);
    }

    public function test_invoice_pdf_and_screen_show_product_and_material_lines(): void
    {
        [, $invoice] = $this->invoicedQuotation();
        $invoice->load('quotation.items.product', 'quotation.items.material', 'customer', 'quotation.user');

        $pdf = html_entity_decode(view('invoices.pdf', compact('invoice'))->render());
        foreach (['Rubber Roll', 'Grey, 2 mm', '2.5 Mtr', 'PVC Granules', 'Bag of 25 kg', '4 Kg', '3904'] as $expected) {
            $this->assertStringContainsString($expected, $pdf, "invoice pdf: $expected");
        }

        $screen = html_entity_decode($this->actingAs($this->admin)->get(route('invoices.show', $invoice))->assertOk()->getContent());
        foreach (['Rubber Roll', 'PVC Granules', 'Bag of 25 kg', '4 Kg', '₹50.00'] as $expected) {
            $this->assertStringContainsString($expected, $screen, "invoice screen: $expected");
        }
    }

    public function test_delivery_challan_pdf_and_screen_show_quantity_and_unit(): void
    {
        [, , $challan] = $this->invoicedQuotation();
        $challan->load('invoice.customer', 'invoice.quotation.items.product', 'invoice.quotation.items.material');

        $pdf = html_entity_decode(view('delivery-challans.pdf', ['deliveryChallan' => $challan])->render());
        foreach (['Rubber Roll', 'Grey, 2 mm', 'PVC Granules', 'Bag of 25 kg', '3904', 'Kg', 'Mtr'] as $expected) {
            $this->assertStringContainsString($expected, $pdf, "challan pdf: $expected");
        }
        $this->assertStringNotContainsString('Total Mtr', $pdf);

        $screen = $this->actingAs($this->admin)->get(route('delivery-challans.show', $challan))->assertOk()->getContent();
        $this->assertStringContainsString('PVC Granules', $screen);
        $this->assertStringNotContainsString('Total Mtr', $screen);
    }

    public function test_an_old_quotation_still_produces_all_three_documents(): void
    {
        $quotation = $this->legacyQuotation();
        $this->actingAs($this->admin)->post(route('quotations.mark-sent', $quotation))->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('quotations.generate-invoice', $quotation), [
            'invoice_number' => 'INV-OLD-1', 'invoice_date' => '2026-09-21',
        ])->assertSessionHasNoErrors();
        $invoice = Invoice::where('quotation_id', $quotation->id)->firstOrFail();
        $this->actingAs($this->admin)->post(route('delivery-challans.store', $invoice));
        $challan = DeliveryChallan::where('invoice_id', $invoice->id)->firstOrFail();

        foreach ([route('quotations.download', $quotation), route('invoices.download', $invoice), route('delivery-challans.download', $challan)] as $url) {
            $this->assertStringStartsWith('%PDF', $this->actingAs($this->admin)->get($url)->assertOk()->getContent(), $url);
        }

        $html = html_entity_decode(view('quotations.pdf', ['quotation' => $quotation->load('items.product', 'items.material', 'customer', 'user')])->render());
        $this->assertStringContainsString('Size: 10.00 Mtr', $html);
        $this->assertStringContainsString('3 Rolls', $html);
        $this->assertStringContainsString('Total Qty', $html);
    }

    // ------------------------------------------------------------------ last rate

    private function approvedQuotationFor(Customer $customer): Quotation
    {
        $quotation = Quotation::create([
            'quotation_number' => 'QUO-APP-' . uniqid(), 'customer_id' => $customer->id, 'user_id' => $this->admin->id,
            'quotation_date' => '2026-08-01', 'status' => 'approved', 'approved_at' => now(), 'document_status' => 'quotation_sent',
            'sub_total' => 0, 'total_amount' => 0,
        ]);
        QuotationItem::create(['quotation_id' => $quotation->id, 'product_id' => $this->product->id, 'no_of_rolls' => 2, 'price_per_mtr' => 75, 'amount' => 150]);
        QuotationItem::create(['quotation_id' => $quotation->id, 'material_id' => $this->material->id, 'no_of_rolls' => 4, 'price_per_mtr' => 12.5, 'amount' => 50]);

        return $quotation;
    }

    public function test_last_rate_is_found_for_a_product_and_for_a_material(): void
    {
        $this->approvedQuotationFor($this->customer);
        $admin = $this->actingAs($this->admin);

        $admin->getJson(route('quotations.last-price', ['customer_id' => $this->customer->id, 'item' => $this->productKey()]))
            ->assertOk()->assertJson(['found' => true, 'rate' => 75]);
        $admin->getJson(route('quotations.last-price', ['customer_id' => $this->customer->id, 'item' => $this->materialKey()]))
            ->assertOk()->assertJson(['found' => true, 'rate' => 12.5]);
    }

    public function test_last_rate_is_per_customer_and_ignores_unapproved_quotations(): void
    {
        $this->approvedQuotationFor($this->customer);
        $someoneElse = Customer::create(['name' => 'Other Co', 'created_by' => $this->admin->id]);
        $admin = $this->actingAs($this->admin);

        $admin->getJson(route('quotations.last-price', ['customer_id' => $someoneElse->id, 'item' => $this->productKey()]))
            ->assertOk()->assertJson(['found' => false, 'rate' => null]);

        Quotation::query()->update(['status' => 'draft']);
        $admin->getJson(route('quotations.last-price', ['customer_id' => $this->customer->id, 'item' => $this->productKey()]))
            ->assertOk()->assertJson(['found' => false]);
    }

    public function test_last_rate_copes_with_a_malformed_item_and_requires_one(): void
    {
        $admin = $this->actingAs($this->admin);

        $admin->getJson(route('quotations.last-price', ['customer_id' => $this->customer->id, 'item' => 'nonsense']))
            ->assertOk()->assertJson(['found' => false]);
        $admin->getJson(route('quotations.last-price', ['customer_id' => $this->customer->id]))
            ->assertStatus(422);
    }

    // ------------------------------------------------------------------ duplicate + delete guard

    public function test_duplicating_a_quotation_copies_material_lines_descriptions_qty_and_rate(): void
    {
        $original = $this->storeQuotation([
            $this->line($this->productKey(), 2.5, 100, 'Grey, 2 mm'),
            $this->line($this->materialKey(), 4, 50, 'Bag of 25 kg'),
        ]);

        $this->actingAs($this->admin)->post(route('quotations.duplicate', $original))->assertSessionHasNoErrors();

        $copy = Quotation::latest('id')->firstOrFail();
        $this->assertNotSame($original->id, $copy->id);
        $shape = fn (Quotation $q) => $q->items()->orderBy('id')->get()
            ->map(fn ($i) => [$i->product_id, $i->material_id, $i->description, (string) $i->qty, (string) $i->rate, (string) $i->amount])->all();
        $this->assertSame($shape($original), $shape($copy));
        $this->assertEquals($original->fresh()->sub_total, $copy->fresh()->sub_total);
    }

    public function test_a_material_used_on_a_quotation_cannot_be_deleted(): void
    {
        $this->storeQuotation([$this->line($this->materialKey())]);
        $unused = Material::create(['name' => 'Unused', 'unit' => 'Nos', 'hsn_code' => '8536', 'status' => 'active']);

        $this->actingAs($this->admin)->delete(route('materials.destroy', $this->material))
            ->assertSessionHas('error', 'Cannot delete a material used in quotations. Mark it inactive instead.');
        $this->assertDatabaseHas('materials', ['id' => $this->material->id]);

        $this->actingAs($this->admin)->delete(route('materials.destroy', $unused))->assertRedirect(route('materials.index'));
        $this->assertDatabaseMissing('materials', ['id' => $unused->id]);
    }

    public function test_a_product_used_on_a_quotation_still_cannot_be_deleted(): void
    {
        $this->storeQuotation([$this->line($this->productKey())]);

        $this->actingAs($this->admin)->delete(route('products.destroy', $this->product))->assertSessionHas('error');
        $this->assertDatabaseHas('products', ['id' => $this->product->id]);
    }

    // ------------------------------------------------------------------ the State dropdown

    public function test_quotation_form_has_a_state_dropdown_fed_by_the_state_master(): void
    {
        State::create(['name' => 'Ghost Land', 'status' => 'inactive']);
        State::create(['name' => 'Test Territory', 'status' => 'active']);

        $html = $this->actingAs($this->admin)->get(route('quotations.create'))->assertOk()->getContent();

        $this->assertSame(1, preg_match('/<select[^>]*name="shipping_state"[^>]*>(.*?)<\/select>/s', $html, $m), 'a <select name="shipping_state"> exists');
        $this->assertStringContainsString('<option value="Gujarat"', $m[1]);
        $this->assertStringContainsString('<option value="Test Territory"', $m[1]);
        $this->assertStringNotContainsString('Ghost Land', $m[1]);
        $this->assertSame(State::where('status', 'active')->count(), substr_count($m[1], '<option value="') - substr_count($m[1], '<option value="">'));
        // choosing a customer pre-fills the state from the customer's record
        $this->assertStringContainsString('data-state="Gujarat"', $html);
    }

    public function test_the_chosen_state_decides_between_cgst_sgst_and_igst(): void
    {
        $home = $this->storeQuotation([$this->line($this->productKey(), 10, 100)], ['gst_applicable' => 1, 'shipping_state' => 'Gujarat']);
        $away = $this->storeQuotation([$this->line($this->productKey(), 10, 100)], ['gst_applicable' => 1, 'shipping_state' => 'Maharashtra']);

        $home->refresh();
        $this->assertEquals([90, 90, 0], [(float) $home->cgst_amount, (float) $home->sgst_amount, (float) $home->igst_amount]);
        $away->refresh();
        $this->assertEquals([0, 0, 180], [(float) $away->cgst_amount, (float) $away->sgst_amount, (float) $away->igst_amount]);
        $this->assertEquals(1180, $away->total_amount);
    }

    // ------------------------------------------------------------------ the model's small helpers

    #[DataProvider('itemKeys')]
    public function test_item_key_parsing(mixed $key, ?array $expected): void
    {
        $this->assertSame($expected, QuotationItem::parseKey($key));
    }

    public static function itemKeys(): array
    {
        return [
            'product' => ['product:5', ['product', 5]],
            'material' => ['material:12', ['material', 12]],
            'big id' => ['product:123456789', ['product', 123456789]],
            'zero' => ['product:0', null],
            'leading zero' => ['material:05', null],
            'trailing junk' => ['product:5x', null],
            'trailing newline' => ["product:5\n", null],
            'wrong case' => ['PRODUCT:5', null],
            'unknown type' => ['service:5', null],
            'no id' => ['product:', null],
            'empty' => ['', null],
            'null' => [null, null],
            'array' => [['product:5'], null],
            'int' => [5, null],
        ];
    }

    public function test_quantity_labels_drop_pointless_zeros(): void
    {
        $label = fn ($qty) => (new QuotationItem(['no_of_rolls' => $qty]))->qty_label;

        $this->assertSame('12', $label(12));
        $this->assertSame('12.5', $label(12.5));
        $this->assertSame('12.25', $label(12.25));
        $this->assertSame('0.5', $label(0.5));
        $this->assertSame('100', $label(100));
    }

    public function test_a_line_without_an_item_does_not_crash_the_documents(): void
    {
        $item = new QuotationItem(['no_of_rolls' => 1, 'price_per_mtr' => 5, 'amount' => 5]);

        $this->assertSame('-', $item->item_name);
        $this->assertNull($item->item_hsn);
        $this->assertNull($item->item_key);
        $this->assertSame('1', $item->qty_with_unit);
    }
}
