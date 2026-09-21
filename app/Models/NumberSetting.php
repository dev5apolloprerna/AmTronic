<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class NumberSetting extends Model
{
    protected $fillable = [
        'document_type',
        'prefix',
        'postfix',
        'next_number',
        'number_padding',
    ];

    /**
     * Atomically generate and reserve the next document number for the given type.
     * Returns the formatted document number string, e.g. QUO-2026-0001.
     */
    public static function generateNext(string $documentType): string
    {
        return DB::transaction(function () use ($documentType) {
            $setting = self::where('document_type', $documentType)->lockForUpdate()->first();

            if (! $setting) {
                $setting = self::create([
                    'document_type' => $documentType,
                    'prefix' => strtoupper(substr($documentType, 0, 3)) . '-',
                    'postfix' => '',
                    'next_number' => 1,
                    'number_padding' => 4,
                ]);
            }

            $number = $setting->next_number;
            $padded = str_pad((string) $number, $setting->number_padding, '0', STR_PAD_LEFT);
            $formatted = $setting->prefix . $padded . $setting->postfix;

            $setting->increment('next_number');

            return $formatted;
        });
    }
}
