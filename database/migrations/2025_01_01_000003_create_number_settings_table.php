<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('number_settings', function (Blueprint $table) {
            $table->id();
            $table->string('document_type')->unique(); // quotation | invoice
            $table->string('prefix')->nullable();
            $table->string('postfix')->nullable();
            $table->unsignedInteger('next_number')->default(1);
            $table->unsignedTinyInteger('number_padding')->default(4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_settings');
    }
};
