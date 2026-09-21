<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            // Manual, optional discount entered by the user (e.g. customer negotiated ₹34 off).
            $table->decimal('discount_amount', 15, 2)->default(0)->after('gst_amount');
            // Auto-calculated adjustment so total_amount lands on a whole rupee (e.g. 33433.92 -> 33434).
            $table->decimal('round_off', 15, 2)->default(0)->after('discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn(['discount_amount', 'round_off']);
        });
    }
};
