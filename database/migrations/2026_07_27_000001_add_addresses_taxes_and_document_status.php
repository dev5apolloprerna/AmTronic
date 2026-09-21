<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->text('address_line_2')->nullable()->after('address');
            $table->string('state', 100)->nullable()->after('address_line_2');
            $table->string('city', 100)->nullable()->after('state');
            $table->string('pincode', 6)->nullable()->after('city');
        });
        Schema::table('quotations', function (Blueprint $table) {
            $table->boolean('shipping_address_different')->default(false);
            $table->text('shipping_address')->nullable();
            $table->text('shipping_address_line_2')->nullable();
            $table->string('shipping_state', 100)->nullable();
            $table->string('shipping_city', 100)->nullable();
            $table->string('shipping_pincode', 6)->nullable();
            $table->decimal('cgst_amount', 15, 2)->default(0);
            $table->decimal('sgst_amount', 15, 2)->default(0);
            $table->decimal('igst_amount', 15, 2)->default(0);
            $table->string('document_status', 30)->default('quotation_ready');
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->text('shipping_address')->nullable();
            $table->text('shipping_address_line_2')->nullable();
            $table->string('shipping_state', 100)->nullable();
            $table->string('shipping_city', 100)->nullable();
            $table->string('shipping_pincode', 6)->nullable();
            $table->decimal('cgst_amount', 15, 2)->default(0);
            $table->decimal('sgst_amount', 15, 2)->default(0);
            $table->decimal('igst_amount', 15, 2)->default(0);
            $table->string('document_status', 30)->default('invoice_ready');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', fn (Blueprint $t) => $t->dropColumn(['shipping_address','shipping_address_line_2','shipping_state','shipping_city','shipping_pincode','cgst_amount','sgst_amount','igst_amount','document_status']));
        Schema::table('quotations', fn (Blueprint $t) => $t->dropColumn(['shipping_address_different','shipping_address','shipping_address_line_2','shipping_state','shipping_city','shipping_pincode','cgst_amount','sgst_amount','igst_amount','document_status']));
        Schema::table('customers', fn (Blueprint $t) => $t->dropColumn(['address_line_2','state','city','pincode']));
    }
};
