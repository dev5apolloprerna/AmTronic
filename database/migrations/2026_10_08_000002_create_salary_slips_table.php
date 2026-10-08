<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One submitted salary (and its slip) per employee per month.
     * Attendance figures are stored as a snapshot of what was paid.
     */
    public function up(): void
    {
        Schema::create('salary_slips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedTinyInteger('days_in_month');
            $table->decimal('monthly_salary', 12, 2);
            $table->decimal('per_day', 12, 2);
            $table->unsignedTinyInteger('full_days')->default(0);
            $table->unsignedTinyInteger('half_days')->default(0);
            $table->unsignedTinyInteger('absent_days')->default(0);
            $table->decimal('paid_leave', 4, 1)->default(0);
            $table->decimal('unpaid_leave', 4, 1)->default(0);
            $table->decimal('payable_days', 4, 1)->default(0);
            $table->decimal('leave_deduction', 12, 2)->default(0);
            $table->decimal('deduction', 12, 2)->default(0);
            $table->string('deduction_reason')->nullable();
            $table->decimal('incentive', 12, 2)->default(0);
            $table->decimal('net_salary', 12, 2);
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'year', 'month']);
            $table->index(['year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_slips');
    }
};
