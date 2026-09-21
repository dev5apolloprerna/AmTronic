<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Amount actually received (cash/bank/etc.)
            // 'amount' column already holds this.
            // short_amount = shortfall the party didn't pay (rounding off / waived),
            // recorded separately so it's clear it was NOT actually collected in cash/bank.
            $table->decimal('short_amount', 15, 2)->default(0)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('short_amount');
        });
    }
};
