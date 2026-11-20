<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_logs', function (Blueprint $table) {
            // The provider's own id for the event. Providers deliver at least once and resend, so the unique pair is what stops a
            // repeat being stored and processed twice (agreement section 11, layers 4 and 5). Null when a provider sends no id.
            $table->string('event_id', 100)->nullable()->after('event');
            $table->unique(['source', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::table('webhook_logs', function (Blueprint $table) {
            $table->dropUnique(['source', 'event_id']);
            $table->dropColumn('event_id');
        });
    }
};
