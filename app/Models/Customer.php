<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'contact_person',
        'phone',
        'email',
        'address',
        'address_line_2',
        'state',
        'city',
        'pincode',
        'gst_number',
        'opening_balance',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
        ];
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
    public function latestCompanyPayment()
    {
        return $this->hasOne(Payment::class)
            ->whereNotNull('receipt_number')
            ->latestOfMany();
    }
     
    public function ledgers()
    {
        return $this->hasMany(CustomerLedger::class);
    }

    /**
     * Current outstanding balance = opening balance + sum of all ledger entries.
     * Positive = customer owes us. Negative = advance with us.
     */
    public function currentBalance(): float
    {
        return (float) $this->opening_balance + (float) $this->ledgers()->sum('amount');
    }
}