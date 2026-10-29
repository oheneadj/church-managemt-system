<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contributions', function (Blueprint $table) {
            // When the receipt text for this gift was queued. Claimed in one statement, so a gift gets one receipt even if
            // the work that sends it is run twice (D-051). Null = no receipt queued, whether it was skipped or not yet sent.
            $table->timestamp('receipt_queued_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('contributions', function (Blueprint $table) {
            $table->dropColumn('receipt_queued_at');
        });
    }
};
