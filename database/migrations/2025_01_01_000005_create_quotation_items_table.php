<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('size_mtr', 10, 2);   // size of roll in meters
            $table->unsignedInteger('no_of_rolls');
            $table->decimal('total_mtr', 12, 2);  // size_mtr * no_of_rolls
            $table->decimal('price_per_mtr', 12, 2);
            $table->decimal('amount', 15, 2);     // total_mtr * price_per_mtr
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
    }
};
