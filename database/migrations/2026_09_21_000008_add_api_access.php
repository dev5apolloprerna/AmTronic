<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The original enum only allowed draft/approved even though the
        // application has a rejected workflow. A string keeps every exposed
        // API/admin status portable across supported databases.
        Schema::table('quotations', function (Blueprint $table) {
            $table->string('status', 30)->default('draft')->change();
        });

        Schema::table('designations', function (Blueprint $table) {
            $table->boolean('api_only')->default(false)->after('can_login');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('api_token', 64)->nullable()->unique()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('api_token'));
        Schema::table('designations', fn (Blueprint $table) => $table->dropColumn('api_only'));
        Schema::table('quotations', fn (Blueprint $table) => $table->enum('status', ['draft', 'approved'])->default('draft')->change());
    }
};
