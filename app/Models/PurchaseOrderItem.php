<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    protected $fillable = ['product_id', 'quantity', 'rate', 'amount'];
    protected function casts(): array { return ['quantity' => 'decimal:3', 'rate' => 'decimal:2', 'amount' => 'decimal:2']; }
    public function purchaseOrder() { return $this->belongsTo(PurchaseOrder::class); }
    public function product() { return $this->belongsTo(Product::class); }
}
