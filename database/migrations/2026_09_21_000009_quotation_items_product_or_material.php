<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A quotation line is now: an item (a Product OR a Material), a free-text
     * description, a quantity and a rate.
     *
     *  - product_id becomes nullable and material_id is added; exactly one of the
     *    two is set on a line (enforced by the application).
     *  - description is new.
     *  - no_of_rolls now holds the QUANTITY, so it must allow decimals (12.5 Mtr);
     *    price_per_mtr holds the RATE. The old column names are kept on purpose.
     *  - size_mtr / total_mtr are no longer collected, so they become nullable.
     *    Existing quotations keep their values and still display them.
     */
    public function up(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->change();
            $table->decimal('size_mtr', 10, 2)->nullable()->change();
            $table->decimal('total_mtr', 12, 2)->nullable()->change();
            $table->decimal('no_of_rolls', 12, 2)->change();
        });

        Schema::table('quotation_items', function (Blueprint $table) {
            $table->foreignId('material_id')->nullable()->after('product_id')
                ->constrained('materials')->restrictOnDelete();
            $table->text('description')->nullable()->after('material_id');
        });
    }

    /**
     * Only works while every line has a product and a whole-number quantity,
     * and fails loudly otherwise instead of losing data.
     */
    public function down(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('material_id');
            $table->dropColumn('description');
        });

        Schema::table('quotation_items', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable(false)->change();
            $table->decimal('size_mtr', 10, 2)->nullable(false)->change();
            $table->decimal('total_mtr', 12, 2)->nullable(false)->change();
            $table->unsignedInteger('no_of_rolls')->change();
        });
    }
};
