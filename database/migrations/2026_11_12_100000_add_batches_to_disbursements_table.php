<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A bank payout needs at least GHS 100 (D-072), so a church that settles to a bank is paid several gifts at once. The payout that goes
 * to the bank is a row of its own with no gift (`contribution_id` is empty, which the unique index allows), and each gift's own row
 * points to it with `batch_id`. Each gift keeps its own row, so what is waiting for a church is always the sum of its unpaid rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disbursements', function (Blueprint $table): void {
            $table->foreignUlid('contribution_id')->nullable()->change();
            $table->foreignUlid('batch_id')->nullable()->after('contribution_id')->constrained('disbursements')->restrictOnDelete();
            $table->index('batch_id');
        });
    }

    public function down(): void
    {
        Schema::table('disbursements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('batch_id');
        });
    }
};
