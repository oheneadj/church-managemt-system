<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A gift given on the public giving page has no staff member to record it (D-073), so `recorded_by` may now be empty. The giver
 * may type a name (kept encrypted, it is personal), and the phone they paid from is kept only as a keyed hash, so a repeated
 * attempt can be recognised and a member matched, without storing the number itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contributions', function (Blueprint $table): void {
            $table->foreignUlid('recorded_by')->nullable()->change();
            $table->text('giver_name')->nullable()->after('notes');
            $table->char('payer_phone_hash', 64)->nullable()->after('giver_name');
            $table->index(['church_id', 'payer_phone_hash', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('contributions', function (Blueprint $table): void {
            $table->dropIndex(['church_id', 'payer_phone_hash', 'status']);
            $table->dropColumn(['giver_name', 'payer_phone_hash']);
        });
    }
};
