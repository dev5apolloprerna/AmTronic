<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_po_and_retrieve_previous_vendor_buy_rate(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $vendor = Vendor::create(['name'=>'Supplier','mobile'=>'9876543210']);
        $product = Product::create(['name'=>'Bracket','unit'=>'Nos','hsn_code'=>'8302','status'=>'active']);
        $response = $this->actingAs($admin)->post(route('purchase-orders.store'), [
            'po_number'=>'PO-1001','vendor_id'=>$vendor->id,'order_date'=>'2026-09-22',
            'items'=>[['product_id'=>$product->id,'quantity'=>3,'rate'=>125.50]],
        ]);
        $order = PurchaseOrder::firstOrFail();
        $response->assertRedirect(route('purchase-orders.show',$order));
        $this->assertDatabaseHas('purchase_orders',['total_amount'=>376.50]);
        $this->getJson(route('purchase-orders.last-rate',['vendor_id'=>$vendor->id,'product_id'=>$product->id]))
            ->assertOk()->assertJson(['rate'=>'125.50','order_date'=>'2026-09-22']);
        $this->get(route('purchase-orders.download',$order))->assertOk()->assertHeader('content-type','application/pdf');
    }

    public function test_employee_cannot_manage_purchase_orders(): void
    {
        $employee = User::factory()->create(['role'=>'user']);
        $this->actingAs($employee)->get(route('purchase-orders.index'))->assertForbidden();
    }
}
