<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A gift now has a status (D-053, D-069 step d). Every gift recorded so far is cash, which is completed the moment it is
 * recorded, so the default of completed is correct for all existing rows. The index serves the polling job that looks for
 * digital gifts still pending.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contributions', function (Blueprint $table): void {
            $table->string('status', 30)->default('completed')->after('payment_method');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('contributions', function (Blueprint $table): void {
            $table->dropIndex(['status', 'created_at']);
            $table->dropColumn('status');
        });
    }
};
