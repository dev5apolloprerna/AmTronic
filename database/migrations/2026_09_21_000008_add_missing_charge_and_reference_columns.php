<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catch-up for columns the application already reads and writes
     * (Quotation / Invoice fillables and QuotationController) but that no
     * earlier migration created - they were added to the live database by hand.
     * Without them a database built from the migrations cannot save a quotation.
     *
     * Every column is guarded with hasColumn(), so on a database that already
     * has them (your live one) this does nothing.
     */
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            if (! Schema::hasColumn('quotations', 'admin_charges')) {
                $table->decimal('admin_charges', 15, 2)->default(0);
            }
            if (! Schema::hasColumn('quotations', 'material_handling_charges')) {
                $table->decimal('material_handling_charges', 15, 2)->default(0);
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'other_reference')) {
                $table->string('other_reference')->nullable();
            }
            if (! Schema::hasColumn('invoices', 'admin_charges')) {
                $table->decimal('admin_charges', 15, 2)->default(0);
            }
            if (! Schema::hasColumn('invoices', 'material_handling_charges')) {
                $table->decimal('material_handling_charges', 15, 2)->default(0);
            }
        });
    }

    /**
     * Deliberately empty: on a live database these columns existed before this
     * migration, so dropping them here would destroy real data.
     */
    public function down(): void
    {
    }
};
