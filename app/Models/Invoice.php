<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'other_reference',
        'quotation_id',
        'customer_id',
        'invoice_date',
        'sub_total',
        'gst_amount',
        'discount_amount',
        'admin_charges',
        'material_handling_charges',
        'round_off',
        'total_amount',
        'shipping_address', 'shipping_address_line_2', 'shipping_state', 'shipping_city', 'shipping_pincode',
        'cgst_amount', 'sgst_amount', 'igst_amount', 'document_status',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'sub_total' => 'decimal:2',
            'gst_amount' => 'decimal:2',
            'cgst_amount' => 'decimal:2', 'sgst_amount' => 'decimal:2', 'igst_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'admin_charges' => 'decimal:2',
            'material_handling_charges' => 'decimal:2',
            'round_off' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
    
    public function deliveryChallan() { return $this->hasOne(DeliveryChallan::class); }

    public function totalPaid(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public function balanceDue(): float
    {
        return (float) $this->total_amount - $this->totalPaid();
    }
}
