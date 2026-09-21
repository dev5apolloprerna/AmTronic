<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\Product;
use App\Models\User;
use App\Rules\HsnCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * HSN codes are 4, 6 or 8 digits. The same rule guards the Product Master and
 * the Material Master.
 */
class HsnCodeTest extends TestCase
{
    use RefreshDatabase;

    public static function validCodes(): array
    {
        return [
            '4 digits (switches/connectors)' => ['8536'],
            '6 digits' => ['390410'],
            '8 digits (ICs)' => ['85423100'],
        ];
    }

    public static function invalidCodes(): array
    {
        return [
            'empty' => [''],
            '3 digits' => ['123'],
            '5 digits (not a real HSN length)' => ['12345'],
            '7 digits' => ['1234567'],
            '9 digits' => ['123456789'],
            'letters' => ['12AB56'],
            'punctuation' => ['85.36'],
        ];
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
    }

    private function productPayload(string $hsn): array
    {
        return ['name' => 'Rubber Roll', 'code' => 'P-' . uniqid(), 'unit' => 'Mtr', 'hsn_code' => $hsn, 'status' => 'active'];
    }

    private function materialPayload(string $hsn): array
    {
        return ['name' => 'PVC Granules', 'code' => 'M-' . uniqid(), 'unit' => 'Kg', 'hsn_code' => $hsn, 'status' => 'active'];
    }

    #[DataProvider('validCodes')]
    public function test_product_accepts_valid_hsn(string $hsn): void
    {
        $this->actingAs($this->admin())->post(route('products.store'), $this->productPayload($hsn))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('products', ['hsn_code' => $hsn]);
    }

    #[DataProvider('invalidCodes')]
    public function test_product_rejects_invalid_hsn(string $hsn): void
    {
        $this->actingAs($this->admin())->post(route('products.store'), $this->productPayload($hsn))
            ->assertSessionHasErrors('hsn_code');

        $this->assertSame(0, Product::count());
    }

    #[DataProvider('validCodes')]
    public function test_material_accepts_valid_hsn(string $hsn): void
    {
        $this->actingAs($this->admin())->post(route('materials.store'), $this->materialPayload($hsn))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('materials', ['hsn_code' => $hsn]);
    }

    #[DataProvider('invalidCodes')]
    public function test_material_rejects_invalid_hsn(string $hsn): void
    {
        $this->actingAs($this->admin())->post(route('materials.store'), $this->materialPayload($hsn))
            ->assertSessionHasErrors('hsn_code');

        $this->assertSame(0, Material::count());
    }

    public function test_hsn_can_be_changed_to_an_eight_digit_code_on_edit(): void
    {
        $admin = $this->admin();
        $product = Product::create($this->productPayload('390410'));
        $material = Material::create($this->materialPayload('390410'));

        $this->actingAs($admin)->put(route('products.update', $product), $this->productPayload('85423100') + ['code' => $product->code])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->put(route('materials.update', $material), $this->materialPayload('85423100') + ['code' => $material->code])
            ->assertSessionHasNoErrors();

        $this->assertSame('85423100', $product->fresh()->hsn_code);
        $this->assertSame('85423100', $material->fresh()->hsn_code);
    }

    public function test_forms_let_the_browser_accept_four_and_eight_digit_codes(): void
    {
        $admin = $this->admin();

        foreach ([route('products.create'), route('materials.create')] as $url) {
            $html = $this->actingAs($admin)->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('maxlength="8"', $html);
            $this->assertStringContainsString('minlength="4"', $html);
            $this->assertStringNotContainsString('[0-9]{5,6}', $html);
        }
    }

    public function test_rule_does_not_let_a_trailing_newline_through(): void
    {
        // "$" would accept "8536\n"; the rule must anchor with \z.
        $passes = fn (mixed $v) => Validator::make(['h' => $v], ['h' => [new HsnCode]])->passes();

        $this->assertTrue($passes('8536'));
        $this->assertFalse($passes("8536\n"));
        $this->assertTrue($passes(8536), 'a JSON client may send the code as a number');
        $this->assertFalse($passes(853), 'a number that is too short is still rejected');
        $this->assertFalse($passes(['8536']));
    }
}
