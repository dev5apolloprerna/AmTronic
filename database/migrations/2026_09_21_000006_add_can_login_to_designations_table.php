<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A designation with can_login = true (e.g. "Sales") lets its employees log
     * in - to this web app now, and to the Android app later. Super Admins can
     * always log in. Employees of any other designation are records only
     * (attendance, advances, ...) and cannot log in.
     *
     * Before this migration every regular user could log in, so it backfills to
     * make sure nobody loses access just by running it.
     */
    public function up(): void
    {
        Schema::table('designations', function (Blueprint $table) {
            $table->boolean('can_login')->default(false)->after('name');
        });

        // 1) Existing regular users with no designation are the sales staff:
        //    put them under "Sales" (reusing it if it already exists).
        if (DB::table('users')->where('role', 'user')->whereNull('designation_id')->exists()) {
            $now = now();

            $salesId = DB::table('designations')->where('name', 'Sales')->value('id')
                ?? DB::table('designations')->insertGetId([
                    'name' => 'Sales',
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

            DB::table('users')
                ->where('role', 'user')
                ->whereNull('designation_id')
                ->update(['designation_id' => $salesId]);
        }

        // 2) Any designation already held by a regular user keeps its holders' access.
        DB::table('designations')
            ->whereIn('id', DB::table('users')->where('role', 'user')->whereNotNull('designation_id')->select('designation_id'))
            ->update(['can_login' => true]);
    }

    public function down(): void
    {
        Schema::table('designations', function (Blueprint $table) {
            $table->dropColumn('can_login');
        });
    }
};
