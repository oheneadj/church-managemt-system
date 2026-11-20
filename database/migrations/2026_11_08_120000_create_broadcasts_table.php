<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A broadcast is one message a staff member sent to many members (D-065). The row is the record of who sent what, to whom
 * (by filter) and how it went, and each text it produces is a `message_logs` line that points back at it. A unique index
 * allows one line for each member of a broadcast, so a worker that runs twice never texts anyone twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcasts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('church_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('sent_by')->constrained('users')->restrictOnDelete();
            $table->string('channel', 30);
            $table->text('message');
            $table->json('filters')->nullable();
            $table->unsignedSmallInteger('parts_per_message');
            $table->unsignedInteger('recipient_count')->nullable();
            $table->string('status', 30)->default('queued');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['church_id', 'created_at']);
        });

        Schema::table('message_logs', function (Blueprint $table) {
            $table->ulid('broadcast_id')->nullable()->after('church_id');
            $table->index('broadcast_id');
        });

        DB::statement('CREATE UNIQUE INDEX message_logs_broadcast_recipient_unique ON message_logs (broadcast_id, recipient_id) WHERE broadcast_id IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS message_logs_broadcast_recipient_unique');

        Schema::table('message_logs', function (Blueprint $table) {
            $table->dropIndex(['broadcast_id']);
            $table->dropColumn('broadcast_id');
        });

        Schema::dropIfExists('broadcasts');
    }
};
