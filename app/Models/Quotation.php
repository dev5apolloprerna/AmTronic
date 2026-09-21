<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quotation extends Model
{
    use HasFactory;

    protected $fillable = [
        'quotation_number',
        'customer_id',
        'user_id',
        'quotation_date',
        'status',
        'gst_applicable',
        'sub_total',
        'gst_amount',
        'discount_amount',
        'admin_charges',
        'material_handling_charges',
        'round_off',
        'total_amount',
        'approved_by',
        'approved_at',
        'shipping_address_different', 'shipping_address', 'shipping_address_line_2',
        'shipping_state', 'shipping_city', 'shipping_pincode',
        'cgst_amount', 'sgst_amount', 'igst_amount', 'document_status',
    ];

    protected function casts(): array
    {
        return [
            'quotation_date' => 'date',
            'gst_applicable' => 'boolean',
                        'shipping_address_different' => 'boolean',
            'sub_total' => 'decimal:2',
            'gst_amount' => 'decimal:2',
            'cgst_amount' => 'decimal:2', 'sgst_amount' => 'decimal:2', 'igst_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'admin_charges' => 'decimal:2',
            'material_handling_charges' => 'decimal:2',
            'round_off' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    const GST_RATE = 18;

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items()
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft' && $this->document_status !== 'quotation_sent';
    }

    public function isSent(): bool
    {
        return $this->status === 'draft' && $this->document_status === 'quotation_sent';
    }

    public function displayStatus(): string
    {
        if ($this->invoice?->document_status === 'invoice_approved') {
            return 'Invoice Sent';
        }

        if ($this->isSent()) {
            return 'Quotation Sent';
        }

        return match ($this->status) {
            'draft' => 'Quotation Created',
            'approved' => 'Quotation Approved',
            default => ucfirst($this->status),
        };
    }

    public function displayStatusClass(): string
    {
        if ($this->invoice?->document_status === 'invoice_approved') {
            return 'invoice-sent';
        }
        
        return $this->isSent() ? 'sent' : $this->status;
    }

    /**
     * Recalculate sub_total / gst_amount / round_off / total_amount from line items.
     * Order: sub total -> - discount -> + charges -> + GST (calculated
     * on the adjusted amount) -> round to nearest whole rupee, recording the
     * adjustment as round_off.
     */
    public function recalculateTotals(): void
    {
        $subTotal = $this->items()->sum('amount');
        $discount = (float) ($this->discount_amount ?? 0);
        $adminCharges = (float) ($this->admin_charges ?? 0);
        $materialHandlingCharges = (float) ($this->material_handling_charges ?? 0);
        $taxableAmount = $subTotal - $discount + $adminCharges + $materialHandlingCharges;
        $gstAmount = $this->gst_applicable ? round($taxableAmount * self::GST_RATE / 100, 2) : 0;

        $beforeRounding = $taxableAmount + $gstAmount;
        $roundedTotal = round($beforeRounding);
        $roundOff = round($roundedTotal - $beforeRounding, 2);
        
        $state = strtolower(trim((string) ($this->shipping_state ?: $this->customer?->state)));
        $this->cgst_amount = $this->gst_applicable && $state === 'gujarat' ? round($gstAmount / 2, 2) : 0;
        $this->sgst_amount = $this->gst_applicable && $state === 'gujarat' ? $gstAmount - $this->cgst_amount : 0;
        $this->igst_amount = $this->gst_applicable && $state !== 'gujarat' ? $gstAmount : 0;
        $this->sub_total = $subTotal;
        $this->gst_amount = $gstAmount;
        $this->discount_amount = $discount;
        $this->admin_charges = $adminCharges;
        $this->material_handling_charges = $materialHandlingCharges;
        $this->round_off = $roundOff;
        $this->total_amount = $roundedTotal;
        $this->save();
    }
}
