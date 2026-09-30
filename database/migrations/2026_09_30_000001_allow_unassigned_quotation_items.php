<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropForeign(['quotation_id']);
            $table->unsignedBigInteger('quotation_id')->default(0)->change();
            $table->foreignId('user_id')->nullable()->after('quotation_id')
                ->constrained('users')->cascadeOnDelete();
            $table->index(['quotation_id', 'user_id']);
        });
    }

    public function down(): void
    {
        // Rows without a quotation cannot satisfy the restored foreign key.
        DB::table('quotation_items')->where('quotation_id', 0)->delete();

        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropIndex(['quotation_id', 'user_id']);
            $table->dropConstrainedForeignId('user_id');
            $table->unsignedBigInteger('quotation_id')->change();
            $table->foreign('quotation_id')->references('id')->on('quotations')->cascadeOnDelete();
        });
    }
};
