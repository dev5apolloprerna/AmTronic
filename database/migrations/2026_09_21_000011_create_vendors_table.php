<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('mobile', 15);
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('gst_number', 15)->nullable();
            $table->timestamps();

            $table->index('name');
            $table->index('mobile');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
