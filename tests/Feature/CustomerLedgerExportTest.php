<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerLedgerExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_ledger_report_offers_excel_and_pdf_exports(): void
    {
        [$admin, $customer] = $this->ledgerFixture();

        $this->actingAs($admin)
            ->get(route('reports.customer-ledger', ['customer_id' => $customer->id]))
            ->assertOk()
            ->assertSee('Export to Excel')
            ->assertSee('Download PDF');
    }

    public function test_filtered_ledger_can_be_exported_to_excel(): void
    {
        [$admin, $customer] = $this->ledgerFixture();

        $response = $this->actingAs($admin)->get(route('reports.customer-ledger.excel', [
            'customer_id' => $customer->id,
            'from_date' => '2026-08-01',
            'to_date' => '2026-08-31',
        ]));

        $response->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertDownload('test-customer-ledger-'.now()->format('Y-m-d').'.xlsx');

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        $this->assertStringContainsString('Invoice INV-001', $sheet);
        $this->assertStringNotContainsString('Old transaction', $sheet);
    }

    public function test_filtered_ledger_can_be_downloaded_as_pdf(): void
    {
        [$admin, $customer] = $this->ledgerFixture();

        $this->actingAs($admin)
            ->get(route('reports.customer-ledger.pdf', ['customer_id' => $customer->id]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('test-customer-ledger-'.now()->format('Y-m-d').'.pdf');
    }

    private function ledgerFixture(): array
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $customer = Customer::create([
            'name' => 'Test Customer',
            'opening_balance' => 100,
            'created_by' => $admin->id,
        ]);
        $customer->ledgers()->create([
            'transaction_date' => '2026-07-15',
            'amount' => 25,
            'description' => 'Old transaction',
            'reference_type' => 'adjustment',
            'entered_by' => $admin->id,
            'balance_after' => 125,
        ]);
        $customer->ledgers()->create([
            'transaction_date' => '2026-08-10',
            'amount' => 200,
            'description' => 'Invoice INV-001',
            'reference_type' => 'invoice',
            'entered_by' => $admin->id,
            'balance_after' => 325,
        ]);

        return [$admin, $customer];
    }
}
