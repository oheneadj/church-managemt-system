<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row for each time a member agreed to receive something (agreement section 36): what, when, under which
     * version of the privacy policy, who recorded it, and when they took it back. Withdrawing sets withdrawn_at; a new
     * agreement later is a new row, so the whole history is kept.
     */
    public function up(): void
    {
        Schema::create('consents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('church_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('member_id')->constrained()->restrictOnDelete();
            $table->string('purpose', 30); // ConsentPurpose: sms, whatsapp
            $table->timestamp('consented_at');
            $table->timestamp('withdrawn_at')->nullable();
            $table->string('policy_version', 20); // the privacy policy the member agreed under
            $table->foreignUlid('recorded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['church_id', 'member_id', 'purpose']);
        });

        // The database itself guarantees a member has at most one open agreement for each purpose.
        DB::statement('CREATE UNIQUE INDEX consents_member_id_purpose_open_unique ON consents (member_id, purpose) WHERE withdrawn_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('consents');
    }
};
