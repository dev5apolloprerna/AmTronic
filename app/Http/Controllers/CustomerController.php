<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');

        $customers = Customer::withSum('ledgers', 'amount')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('gst_number', 'like', "%{$search}%")
                        ->orWhere('state', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $customers->getCollection()->transform(function ($customer) {
            $customer->balance = (float) $customer->opening_balance + (float) ($customer->ledgers_sum_amount ?? 0);
            return $customer;
        });

        return view('customers.index', compact('customers', 'search'));
    }

    public function create()
    {
        return view('customers.create');
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        DB::transaction(function () use ($data) {
            $data['created_by'] = Auth::id();
            $customer = Customer::create($data);

            if ((float) $customer->opening_balance != 0) {
                CustomerLedger::create([
                    'customer_id' => $customer->id,
                    'transaction_date' => now()->toDateString(),
                    'amount' => 0, // opening balance is stored on customer itself, ledger starts from it
                    'description' => 'Opening balance',
                    'reference_type' => 'opening_balance',
                    'entered_by' => Auth::id(),
                    'balance_after' => $customer->opening_balance,
                ]);
            }
        });

        return redirect()->route('customers.index')->with('success', 'Customer created successfully.');
    }

    public function show(Customer $customer)
    {
        $ledgers = $customer->ledgers()->with('enteredBy')->orderBy('transaction_date')->orderBy('id')->get();
        $balance = $customer->currentBalance();
        $quotations = $customer->quotations()->latest()->take(10)->get();

        return view('customers.show', compact('customer', 'ledgers', 'balance', 'quotations'));
    }

    public function edit(Customer $customer)
    {
        return view('customers.edit', compact('customer'));
    }

    public function update(Request $request, Customer $customer)
    {
        $data = $this->validateData($request, $customer->id);

        $oldOpening = (float) $customer->opening_balance;
        $customer->update($data);

        // If opening balance changed, log an adjustment so ledger stays reconcilable.
        if ($oldOpening != (float) $data['opening_balance']) {
            CustomerLedger::create([
                'customer_id' => $customer->id,
                'transaction_date' => now()->toDateString(),
                'amount' => 0,
                'description' => 'Opening balance adjusted from ' . $oldOpening . ' to ' . $data['opening_balance'],
                'reference_type' => 'adjustment',
                'entered_by' => Auth::id(),
                'balance_after' => $customer->currentBalance(),
            ]);
        }

        return redirect()->route('customers.index')->with('success', 'Customer updated successfully.');
    }

    public function destroy(Customer $customer)
    {
        DB::transaction(function () use ($customer) {
            $quotations = $customer->quotations()->with('invoice.payments')->get();

            foreach ($quotations as $quotation) {
                if ($quotation->invoice) {
                    $quotation->invoice->payments()->delete();
                    $quotation->invoice->delete();
                }
                $quotation->items()->delete();
                $quotation->delete();
            }

            $customer->ledgers()->delete();
            $customer->delete();
        });

        return redirect()->route('customers.index')
            ->with('success', 'Customer deleted, along with all their quotations, invoices, and payment history.');
    }

    /**
     * Add a manual ledger entry (payment received / advance / adjustment).
     * Can be entered by admin or user.
     */
    public function storeLedgerEntry(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'transaction_date' => ['required', 'date'],
            'entry_type' => ['required', 'in:payment,due_adjustment'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        // Payment received reduces due (negative). A manual due adjustment increases due (positive).
        $signedAmount = $data['entry_type'] === 'payment' ? -abs($data['amount']) : abs($data['amount']);

        DB::transaction(function () use ($customer, $data, $signedAmount) {
            $newBalance = $customer->currentBalance() + $signedAmount;

            CustomerLedger::create([
                'customer_id' => $customer->id,
                'transaction_date' => $data['transaction_date'],
                'amount' => $signedAmount,
                'description' => $data['description'] ?? ($data['entry_type'] === 'payment' ? 'Payment received' : 'Due adjustment'),
                'reference_type' => $data['entry_type'] === 'payment' ? 'payment' : 'adjustment',
                'entered_by' => Auth::id(),
                'balance_after' => $newBalance,
            ]);
        });

        return back()->with('success', 'Ledger entry added successfully.');
    }

    private function validateData(Request $request, $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['required', 'string', 'max:2000'],
            'address_line_2' => ['nullable', 'string', 'max:2000'],
            'state' => ['required', 'string', 'in:' . implode(',', config('states'))],
            'city' => ['required', 'string', 'max:100'],
            'pincode' => ['required', 'digits:6'],
            'gst_number' => ['nullable', 'string', 'max:50'],
            'opening_balance' => ['required', 'numeric'],
        ]);
    }
}
