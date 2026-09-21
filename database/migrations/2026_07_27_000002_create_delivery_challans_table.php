<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('delivery_challans', function (Blueprint $table) {
            $table->id();
            $table->string('challan_number')->unique();
            $table->foreignId('invoice_id')->unique()->constrained()->cascadeOnDelete();
            $table->date('challan_date');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('delivery_challans'); }
};
