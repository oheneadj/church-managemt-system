<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The last step of expand and contract (patterns/Zero-Downtime Migrations): every row must have its fund name
        // first. This only checks; the filling is the batched command, never a schema migration.
        if (DB::table('contributions')->whereNull('fund_name')->exists()) {
            throw new RuntimeException('Some contributions have no fund_name yet. Run: php artisan contributions:backfill-fund-names');
        }

        Schema::table('contributions', function ($table) {
            $table->string('fund_name', 100)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('contributions', function ($table) {
            $table->string('fund_name', 100)->nullable()->change();
        });
    }
};
