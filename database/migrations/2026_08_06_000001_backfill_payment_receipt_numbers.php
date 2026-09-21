<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('payments')
            ->whereNull('receipt_number')
            ->orderBy('id')
            ->eachById(function ($payments): void {
                foreach ($payments as $payment) {
                    DB::table('payments')
                        ->where('id', $payment->id)
                        ->update(['receipt_number' => 'PR-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT)]);
                }
            });
    }

    public function down(): void
    {
        // Receipt numbers are retained because they may already have been issued to customers.
    }
};
