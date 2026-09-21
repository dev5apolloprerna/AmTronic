<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One line of a quotation: an item (a Product OR a Material), an optional
 * description, a quantity and a rate.
 *
 * Storage note: the quantity is kept in `no_of_rolls` and the rate in
 * `price_per_mtr` (their original names, from when lines were priced per roll).
 * Use the `qty` / `rate` accessors below; do not rely on the column names.
 * `size_mtr` / `total_mtr` / `despatch_to` are only present on lines created
 * before the change and are still shown on those.
 */
class QuotationItem extends Model
{
    use HasFactory;

    public const TYPE_PRODUCT = 'product';
    public const TYPE_MATERIAL = 'material';

    protected $fillable = [
        'quotation_id',
        'product_id',
        'material_id',
        'description',
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
            'no_of_rolls' => 'decimal:2',
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

    public function material()
    {
        return $this->belongsTo(Material::class);
    }

    // ------------------------------------------------------------------ form key

    /**
     * The form submits the chosen item as one value: "product:12" or "material:7".
     *
     * @return array{0: string, 1: int}|null [type, id], or null if malformed
     */
    public static function parseKey(mixed $key): ?array
    {
        if (! is_string($key) || ! preg_match('/^(product|material):([1-9]\d{0,17})\z/', $key, $m)) {
            return null;
        }

        return [$m[1], (int) $m[2]];
    }

    /** The value the item dropdown uses for this line ("product:12" / "material:7"). */
    public function getItemKeyAttribute(): ?string
    {
        if ($this->product_id) {
            return self::TYPE_PRODUCT . ':' . $this->product_id;
        }

        return $this->material_id ? self::TYPE_MATERIAL . ':' . $this->material_id : null;
    }

    // ------------------------------------------------------------------ what the documents show

    /** The Product or Material this line is for. */
    public function getItemAttribute(): Product|Material|null
    {
        return $this->product ?? $this->material;
    }

    public function getItemNameAttribute(): string
    {
        return $this->item?->name ?? '-';
    }

    public function getItemHsnAttribute(): ?string
    {
        return $this->item?->hsn_code;
    }

    /**
     * Lines made before the change were counted in rolls, so they keep saying
     * "Rolls"; everything else uses the unit of its Product / Material.
     */
    public function getItemUnitAttribute(): string
    {
        return $this->isLegacyRollLine() ? 'Rolls' : ($this->item?->unit ?: '');
    }

    /** The line's own description, else the master's description (what older quotations showed). */
    public function getLineDescriptionAttribute(): ?string
    {
        $own = trim((string) $this->description);

        return $own !== '' ? $own : ($this->item?->description ?: null);
    }

    /** "Size: 10.00 Mtr" for lines made before the change, otherwise null. */
    public function getLegacySizeLabelAttribute(): ?string
    {
        return $this->isLegacyRollLine() ? 'Size: ' . number_format((float) $this->size_mtr, 2) . ' Mtr' : null;
    }

    public function isLegacyRollLine(): bool
    {
        return (float) $this->size_mtr > 0;
    }

    // ------------------------------------------------------------------ quantity / rate

    public function getQtyAttribute(): float
    {
        return (float) ($this->attributes['no_of_rolls'] ?? 0);
    }

    public function getRateAttribute(): float
    {
        return (float) ($this->attributes['price_per_mtr'] ?? 0);
    }

    /** 12.00 -> "12", 12.50 -> "12.5", 12.25 -> "12.25" */
    public function getQtyLabelAttribute(): string
    {
        return rtrim(rtrim(number_format($this->qty, 2, '.', ''), '0'), '.');
    }

    /** "12 Mtr" / "3 Nos" / "10 Rolls" */
    public function getQtyWithUnitAttribute(): string
    {
        return trim($this->qty_label . ' ' . $this->item_unit);
    }
}
