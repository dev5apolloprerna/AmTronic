<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'unit',
        'hsn_code',
        'status',
    ];

    public function quotationItems()
    {
        return $this->hasMany(QuotationItem::class);
    }
}
