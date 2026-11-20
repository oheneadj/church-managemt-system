<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A message sent before a church exists (the sign-up code) belongs to no church, so church_id may be empty.
     * Church screens never see those rows: the church scope only returns rows of the current church.
     */
    public function up(): void
    {
        Schema::table('message_logs', function (Blueprint $table) {
            $table->ulid('church_id')->nullable()->change();
        });
    }

    /**
     * Puts the NOT NULL rule back. The rows with no church exist only because of this migration, so they are
     * removed first; otherwise the rule cannot be restored.
     */
    public function down(): void
    {
        DB::table('message_logs')->whereNull('church_id')->delete();

        Schema::table('message_logs', function (Blueprint $table) {
            $table->ulid('church_id')->nullable(false)->change();
        });
    }
};
