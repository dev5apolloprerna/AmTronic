<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Invoice;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use NumberFormatter;

class PaymentController extends Controller
{
    /**
     * Show a concise customer-wise list of pending balances.
     */
    public function pendingAmounts()
    {
        $data = $this->pendingAmountsData();

        return view('payments.pending-amounts', $data);
    }

    /**
     * Download the customer-wise pending payment report as an Excel-compatible file.
     */
    public function pendingAmountsExcel()
    {
        return response()
            ->view('payments.pending-amounts-excel', $this->pendingAmountsData())
            ->header('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="pending-payments-'.now()->format('Y-m-d').'.xls"');
    }

    private function pendingAmountsData(): array
    {
        $customers = Customer::query()
            ->withSum('ledgers as ledger_balance', 'amount')
            ->orderBy('name')
            ->get()
            ->each(function (Customer $customer) {
                $customer->pending_amount = max(
                    0,
                    (float) $customer->opening_balance + (float) ($customer->ledger_balance ?? 0)
                );
            })
            ->filter(fn (Customer $customer) => $customer->pending_amount > 0);

        $totalPending = $customers->sum('pending_amount');

        return compact('customers', 'totalPending');
    }

    
    /**
     * Record a payment collected against a specific approved quotation's invoice.
     */
    public function index(Request $request)
    {
       
        $companySearch = $request->string('company')->trim()->limit(100)->toString();

        // Only invoices that have actually been generated should be considered.
        $generatedInvoice = function ($query) {
            $query->whereNotNull('invoice_number')
                ->where('invoice_number', '!=', '');
        };

        $companySuggestions = Customer::query()
            ->whereHas('invoices', $generatedInvoice)
            ->orderBy('name')
            ->pluck('name');

        $customers = Customer::query()
            ->whereHas('invoices', $generatedInvoice)
            ->when($companySearch !== '', function ($query) use ($companySearch) {
                $query->where('name', 'like', '%'.$companySearch.'%');
            })
            ->withSum([
                'invoices as billed_amount' => $generatedInvoice,
            ], 'total_amount')
            ->withSum('payments as collected_amount', 'amount')
            // ->with('latestCompanyPayment')
            ->orderBy('name')
            ->get();



        foreach ($customers as $customer) {
            $customer->collected_amount = (float) ($customer->collected_amount ?? 0);
            $customer->due_amount = max(0, (float) ($customer->billed_amount ?? 0) - $customer->collected_amount);
        }

        $totals = [
            'billed' => $customers->sum('billed_amount'),
            'collected' => $customers->sum('collected_amount'),
            'due' => $customers->sum('due_amount'),
        ];

        return view('payments.index', compact('customers', 'totals', 'companySearch', 'companySuggestions'));
    }

    public function storeCustomerPayment(Request $request)
    {

        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:cash,cheque,bank_transfer,upi,other'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        abort_unless(
            Invoice::where('customer_id', $data['customer_id'])
                ->whereNotNull('invoice_number')
                ->where('invoice_number', '!=', '')
                ->exists(),
            422,
            'This company does not have a generated invoice.'
        );

        $customer = Customer::findOrFail($data['customer_id']);
        $invoice = Invoice::where('customer_id', $customer->id)
            ->whereNotNull('invoice_number')
            ->where('invoice_number', '!=', '')
            ->latest('id')
            ->firstOrFail();

        $amount = (float) $data['amount'];
/*        if ($amount > max(0, $customer->currentBalance()) + 0.01) {
            return back()->withErrors(['amount' => 'Amount cannot exceed the customer balance (₹'.number_format(max(0, $customer->currentBalance()), 2).').'])->withInput();
        }*/

        $payment = DB::transaction(function () use ($data, $customer, $invoice, $amount) {
           $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'customer_id' => $customer->id,
                'payment_date' => $data['payment_date'],
                'amount' => $amount,
                'payment_method' => $data['payment_method'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'entered_by' => Auth::id(),
            ]);

            $payment->update([
                'receipt_number' => 'PR-'.str_pad(
                    (string) $payment->id,
                    6,
                    '0',
                    STR_PAD_LEFT
                ),
            ]);

            CustomerLedger::create([
                'customer_id' => $customer->id,
                'transaction_date' => $payment->payment_date,
                'amount' => -abs($amount),
                'description' => 'Customer payment received - Receipt '.$payment->receipt_number,
                'reference_type' => 'payment',
                'reference_id' => $payment->id,
                'entered_by' => Auth::id(),
                'balance_after' => $customer->currentBalance() - $amount,
            ]);

            return $payment;
        });

        return redirect()->route('payment-collections.index')
            ->with('success', 'Payment collected successfully. Receipt '.$payment->receipt_number.' generated.');
    }
    public function history(Customer $customer)
    {
        $payments = $customer->payments()
            ->with(['invoice', 'enteredBy'])
            ->latest('payment_date')
            ->latest('id')
            ->get();

        return view('payments.history', [
            'customer' => $customer,
            'payments' => $payments,
            'totalCollected' => (float) $payments->sum('amount'),
        ]);
    }

    public function receipt(Payment $payment)
    {
        abort_unless($payment->receipt_number, 404);

        $payment->load(['customer', 'enteredBy']);

        $amountInWords = $this->amountInWords((float) $payment->amount);
        $dueAmount = max(0, $payment->customer->currentBalance());

        return Pdf::loadView(
            'payments.receipt',
            compact('payment', 'amountInWords', 'dueAmount')
        )
            ->setPaper('a5')
            ->download($payment->receipt_number.'.pdf');
    }

    public function store(Request $request, Invoice $invoice)
    {
        $this->authorizeAccess($invoice);

        $data = $request->validate([
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', 'in:cash,cheque,bank_transfer,upi,other'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $amount = (float) $data['amount'];
        $balanceDue = $invoice->balanceDue();

        if ($amount > $balanceDue + 0.01) {
            return back()->withErrors(['amount' => 'Amount cannot exceed the balance due (₹' . number_format($balanceDue, 2) . ').'])->withInput();
        }

        DB::transaction(function () use ($invoice, $data, $amount) {
            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'payment_date' => $data['payment_date'],
                'amount' => $amount,
                'payment_method' => $data['payment_method'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'entered_by' => Auth::id(),
            ]);

            $customer = $invoice->customer;
            $newBalance = $customer->currentBalance() - $amount;

            CustomerLedger::create([
                'customer_id' => $customer->id,
                'transaction_date' => $payment->payment_date,
                'amount' => -abs($amount),
                'description' => 'Payment received for Invoice ' . $invoice->invoice_number,
                'reference_type' => 'payment',
                'reference_id' => $payment->id,
                'entered_by' => Auth::id(),
                'balance_after' => $newBalance,
            ]);
        });

        return back()->with('success', 'Payment recorded successfully.');
    }

    /**
     * Remove a payment entry (corrections) - Super Admin only, also reverses the ledger entry.
     */
    public function destroy(Payment $payment)
    {
        if (! Auth::user()->isSuperAdmin()) {
            abort(403, 'Only a Super Admin can delete a payment entry.');
        }

        DB::transaction(function () use ($payment) {
            CustomerLedger::where('reference_type', 'payment')
                ->where('reference_id', $payment->id)
                ->delete();

            $payment->delete();
        });

        return back()->with('success', 'Payment entry removed.');
    }

    private function authorizeAccess(Invoice $invoice): void
    {
        $user = Auth::user();
        if (! $user->isSuperAdmin() && $invoice->quotation->user_id !== $user->id) {
            abort(403, 'You do not have permission to record payments for this invoice.');
        }
    }
    private function amountInWords(float $amount): string
    {
        $totalPaise = (int) round($amount * 100);
        $rupees = intdiv($totalPaise, 100);
        $paise = $totalPaise % 100;
        $formatter = new NumberFormatter('en_IN', NumberFormatter::SPELLOUT);
        $words = 'Indian Rupees '.ucfirst($formatter->format($rupees));

        if ($paise > 0) {
            $words .= ' and '.ucfirst($formatter->format($paise)).' Paise';
        }

        return $words.' Only';
    }
}
