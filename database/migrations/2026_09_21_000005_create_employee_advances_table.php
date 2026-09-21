<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per advance given to an employee, with what has been returned
     * against it. Outstanding balance = SUM(adv_amount) - SUM(return_amount).
     */
    public function up(): void
    {
        Schema::create('employee_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->restrictOnDelete();
            $table->decimal('adv_amount', 12, 2);
            $table->date('adv_date');
            $table->decimal('return_amount', 12, 2)->default(0);
            $table->date('return_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_advances');
    }
};
