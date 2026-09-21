<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuotationItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'quotation_id',
        'product_id',
        'despatch_to',
        'size_mtr',
        'no_of_rolls',
        'total_mtr',
        'price_per_mtr',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'size_mtr' => 'decimal:2',
            'total_mtr' => 'decimal:2',
            'price_per_mtr' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
