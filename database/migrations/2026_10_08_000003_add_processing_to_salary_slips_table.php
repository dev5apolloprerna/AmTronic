<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Salary workflow:
     *   submitted  - saved, still editable (draft slip)
     *   processed  - final and locked; can only be removed through
     *                "Delete & Regenerate" (whole month).
     */
    public function up(): void
    {
        Schema::table('salary_slips', function (Blueprint $table) {
            $table->string('status', 20)->default('submitted')->after('net_salary');
            $table->timestamp('processed_at')->nullable()->after('status');
            $table->foreignId('processed_by')->nullable()->after('processed_at')->constrained('users')->nullOnDelete();
            $table->index(['year', 'month', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('salary_slips', function (Blueprint $table) {
            $table->dropIndex(['year', 'month', 'status']);
            $table->dropConstrainedForeignId('processed_by');
            $table->dropColumn(['status', 'processed_at']);
        });
    }
};
