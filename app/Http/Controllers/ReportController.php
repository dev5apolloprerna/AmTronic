<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    /**
     * Customer (vendor) ledger history report - filter by customer + date range.
     */
    public function customerLedger(Request $request)
    {
        $customers = Customer::orderBy('name')->get();

        $customerId = $request->get('customer_id');
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');

        $ledgers = collect();
        $selectedCustomer = null;
        $openingBalanceBeforeRange = 0;

        if ($customerId) {
            $selectedCustomer = Customer::findOrFail($customerId);

            $query = $selectedCustomer->ledgers()->with('enteredBy')->orderBy('transaction_date')->orderBy('id');

            if ($fromDate) {
                $openingBalanceBeforeRange = (float) $selectedCustomer->opening_balance
                    + (float) $selectedCustomer->ledgers()->where('transaction_date', '<', $fromDate)->sum('amount');

                $query->where('transaction_date', '>=', $fromDate);
            } else {
                $openingBalanceBeforeRange = (float) $selectedCustomer->opening_balance;
            }

            if ($toDate) {
                $query->where('transaction_date', '<=', $toDate);
            }

            $ledgers = $query->get();
        }

        return view('reports.customer-ledger', compact(
            'customers', 'ledgers', 'selectedCustomer', 'customerId', 'fromDate', 'toDate', 'openingBalanceBeforeRange'
        ));
    }

    /**
     * Sales report - filter by date range (based on invoice date).
     */
        public function customerLedgerExcel(Request $request)
    {
        $data = $this->ledgerExportData($request);
        $filename = $this->ledgerFilename($data['selectedCustomer']->name, 'xls');

        return response()
            ->view('reports.customer-ledger-excel', $data)
            ->header('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    /**
     * Download the filtered customer ledger as a PDF document.
     */
    public function customerLedgerPdf(Request $request)
    {
        $data = $this->ledgerExportData($request);
        $filename = $this->ledgerFilename($data['selectedCustomer']->name, 'pdf');

        return Pdf::loadView('reports.customer-ledger-pdf', $data)
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }

    private function ledgerExportData(Request $request): array
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
        ]);

        $selectedCustomer = Customer::findOrFail($validated['customer_id']);
        $fromDate = $validated['from_date'] ?? null;
        $toDate = $validated['to_date'] ?? null;
        $openingBalanceBeforeRange = (float) $selectedCustomer->opening_balance;

        $query = $selectedCustomer->ledgers()
            ->with('enteredBy')
            ->orderBy('transaction_date')
            ->orderBy('id');

        if ($fromDate) {
            $openingBalanceBeforeRange += (float) $selectedCustomer->ledgers()
                ->where('transaction_date', '<', $fromDate)
                ->sum('amount');
            $query->where('transaction_date', '>=', $fromDate);
        }

        if ($toDate) {
            $query->where('transaction_date', '<=', $toDate);
        }

        return [
            'selectedCustomer' => $selectedCustomer,
            'ledgers' => $query->get()->reject(fn ($entry) => $entry->reference_type === 'opening_balance'),
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'openingBalanceBeforeRange' => $openingBalanceBeforeRange,
        ];
    }

    private function ledgerFilename(string $customerName, string $extension): string
    {
        $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '-', trim($customerName)) ?: 'customer';

        return strtolower(trim($safeName, '-')).'-ledger-'.now()->format('Y-m-d').'.'.$extension;
    }
    public function sales(Request $request)
    {
        return view('reports.sales', $this->salesReportData($request));
    }

    /**
     * Download the filtered sales report as an Excel workbook.
     */
    public function salesExcel(Request $request)
    {
        $data = $this->salesReportData($request);
        $filename = $this->salesFilename('xls');

        return response()
            ->view('reports.sales-excel', $data)
            ->header('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    /**
     * Download the filtered sales report as a PDF document.
     */
    public function salesPdf(Request $request)
    {
        $data = $this->salesReportData($request);
        $filename = $this->salesFilename('pdf');

        return Pdf::loadView('reports.sales-pdf', $data)
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }

    private function salesReportData(Request $request): array
    {
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');
        $customerId = $request->get('customer_id');

        $customers = Customer::orderBy('name')->get();
        $selectedCustomer = $customerId ? Customer::find($customerId) : null;

        $invoices = Invoice::with(['customer', 'quotation.user'])
            ->when($fromDate, fn ($q) => $q->where('invoice_date', '>=', $fromDate))
            ->when($toDate, fn ($q) => $q->where('invoice_date', '<=', $toDate))
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->orderBy('invoice_date')
            ->get();

        $totals = [
            'sub_total' => $invoices->sum('sub_total'),
            'discount_amount' => $invoices->sum('discount_amount'),
            'gst_amount' => $invoices->sum('gst_amount'),
            'cgst_amount' => $invoices->sum('cgst_amount'),
            'sgst_amount' => $invoices->sum('sgst_amount'),
            'igst_amount' => $invoices->sum('igst_amount'),
            'total_amount' => $invoices->sum('total_amount'),
            'count' => $invoices->count(),
        ];
        $totals['pre_gst_total'] = $totals['sub_total'] - $totals['discount_amount'];

        return compact('invoices', 'totals', 'customers', 'fromDate', 'toDate', 'customerId', 'selectedCustomer');
    }

    private function salesFilename(string $extension): string
    {
        return 'sales-report-'.now()->format('Y-m-d').'.'.$extension;
    }
}