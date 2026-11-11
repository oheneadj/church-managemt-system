<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Why a mobile money gift failed (D-073, D-069 step d3): the provider declined it, refused to start it, or we gave up waiting for
 * the giver to approve (expired). An expired gift is still asked about for a day, because a giver may approve late.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contributions', function (Blueprint $table): void {
            $table->string('failure_reason', 30)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('contributions', function (Blueprint $table): void {
            $table->dropColumn('failure_reason');
        });
    }
};
