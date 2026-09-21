<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            // Manual, optional discount entered per product line.
            $table->decimal('discount_amount', 15, 2)->default(0)->after('amount');
            // Auto adjustment so this line's final amount lands on a whole rupee.
            $table->decimal('round_off', 15, 2)->default(0)->after('discount_amount');
            // Final amount used in the quotation subtotal: amount - discount_amount, rounded.
            $table->decimal('net_amount', 15, 2)->default(0)->after('round_off');
        });
    }

    public function down(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropColumn(['discount_amount', 'round_off', 'net_amount']);
        });
    }
};
