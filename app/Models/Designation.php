<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Designation extends Model
{
    protected $fillable = ['name', 'status', 'can_login', 'api_only'];

    protected function casts(): array
    {
        return [
            'can_login' => 'boolean',
            'api_only' => 'boolean',
        ];
    }

    public function employees()
    {
        return $this->hasMany(User::class, 'designation_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Designations for an employee dropdown: all active ones, plus the
     * employee's current designation even if it has since been made inactive.
     */
    public static function selectable(?int $currentId = null)
    {
        return static::query()
            ->where(function (Builder $q) use ($currentId) {
                $q->where('status', 'active');

                if ($currentId) {
                    $q->orWhere('id', $currentId);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name', 'status', 'can_login']);
    }
}
