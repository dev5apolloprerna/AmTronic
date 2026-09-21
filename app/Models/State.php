<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class State extends Model
{
    /**
     * Company home state. Quotation::recalculateTotals() compares the shipping
     * state against this name to choose CGST + SGST (same state) or IGST
     * (other state), so it must never be renamed, deactivated or deleted.
     */
    public const HOME_STATE = 'Gujarat';

    protected $fillable = ['name', 'status'];

    // Customers, quotations and invoices store the state as its *name*
    // (a plain string), so these relations join on name, not on an id.
    public function customers()
    {
        return $this->hasMany(Customer::class, 'state', 'name');
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class, 'shipping_state', 'name');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'shipping_state', 'name');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * State names for a dropdown: every active state, plus $current if the
     * record being edited holds a value that is no longer in the master.
     *
     * @return array<int, string>
     */
    public static function selectableNames(?string $current = null): array
    {
        $names = static::active()->orderBy('name')->pluck('name')->all();

        if ($current !== null && $current !== '' && ! in_array($current, $names, true)) {
            $names[] = $current;
        }

        return $names;
    }

    public function isHomeState(): bool
    {
        return strcasecmp($this->name, self::HOME_STATE) === 0;
    }

    /**
     * Number of customers / quotations / invoices using this state. Uses the
     * withCount() values when the query loaded them, so the index page does
     * not run three extra queries per row.
     */
    public function usageCount(): int
    {
        return (int) ($this->getAttribute('customers_count') ?? $this->customers()->count())
            + (int) ($this->getAttribute('quotations_count') ?? $this->quotations()->count())
            + (int) ($this->getAttribute('invoices_count') ?? $this->invoices()->count());
    }

    /**
     * Why this state can't be renamed, deactivated or deleted (null = free to change).
     */
    public function lockReason(): ?string
    {
        if ($this->isHomeState()) {
            return self::HOME_STATE . ' is the company home state and drives the CGST/SGST vs IGST calculation, '
                . 'so it cannot be renamed, deactivated or deleted.';
        }

        if ($this->usageCount() > 0) {
            return 'This state is used by existing customers or documents, so it cannot be renamed, '
                . 'deactivated or deleted. Change those records first.';
        }

        return null;
    }
}
