<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    protected $fillable = ['po_number', 'vendor_id', 'order_date', 'notes', 'total_amount', 'created_by'];

    protected function casts(): array
    {
        return ['order_date' => 'date', 'total_amount' => 'decimal:2'];
    }

    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function items() { return $this->hasMany(PurchaseOrderItem::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
