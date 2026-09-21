<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaterialMasterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'PVC Granules',
            'code' => 'MAT-001',
            'unit' => 'Kg',
            'hsn_code' => '390410',
            'status' => 'active',
        ], $overrides);
    }

    public function test_only_super_admin_can_manage_materials(): void
    {
        $sales = User::factory()->create(['role' => 'user', 'status' => 'active']);

        $this->actingAs($sales)->get(route('materials.index'))->assertForbidden();
        $this->actingAs($sales)->post(route('materials.store'), $this->payload())->assertForbidden();
        $this->actingAs($this->admin())->get(route('materials.index'))->assertOk();
    }

    public function test_admin_can_create_list_search_edit_and_delete_a_material(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('materials.store'), $this->payload())
            ->assertRedirect(route('materials.index'));
        $material = Material::where('code', 'MAT-001')->firstOrFail();
        $this->assertSame('390410', $material->hsn_code);

        // searchable by name, code and HSN
        foreach (['Granules', 'MAT-001', '390410'] as $term) {
            $this->actingAs($admin)->get(route('materials.index', ['search' => $term]))
                ->assertOk()->assertSee('PVC Granules');
        }

        $this->actingAs($admin)->put(route('materials.update', $material), $this->payload(['name' => 'PVC Resin', 'unit' => 'Bag']))
            ->assertRedirect(route('materials.index'));
        $this->assertDatabaseHas('materials', ['id' => $material->id, 'name' => 'PVC Resin', 'unit' => 'Bag']);

        $this->actingAs($admin)->delete(route('materials.destroy', $material))->assertRedirect(route('materials.index'));
        $this->assertDatabaseMissing('materials', ['id' => $material->id]);
    }

    public function test_hsn_code_is_required_and_must_be_four_six_or_eight_digits(): void
    {
        $admin = $this->admin();

        foreach (['', '123', '12345', '1234567', '123456789', '12AB56'] as $bad) {
            $this->actingAs($admin)->post(route('materials.store'), $this->payload(['hsn_code' => $bad]))
                ->assertSessionHasErrors('hsn_code');
        }
        $this->assertSame(0, Material::count());

        // 8536 (switches / connectors) is a real 4-digit HSN; see HsnCodeTest for the full matrix.
        $this->actingAs($admin)->post(route('materials.store'), $this->payload(['hsn_code' => '8536']))
            ->assertSessionHasNoErrors();
    }

    public function test_material_code_must_be_unique_but_can_be_kept_on_edit(): void
    {
        $admin = $this->admin();
        $first = Material::create($this->payload());

        $this->actingAs($admin)->post(route('materials.store'), $this->payload(['name' => 'Other']))
            ->assertSessionHasErrors('code');
        $this->actingAs($admin)->put(route('materials.update', $first), $this->payload(['name' => 'PVC Granules v2']))
            ->assertSessionHasNoErrors();
    }

    public function test_material_screens_render(): void
    {
        $admin = $this->admin();
        $material = Material::create($this->payload());

        $this->actingAs($admin)->get(route('materials.create'))->assertOk()->assertSee('Save Material');
        $this->actingAs($admin)->get(route('materials.edit', $material))->assertOk()->assertSee('Update Material')->assertSee('390410');
    }
}
