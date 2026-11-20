<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only change history (patterns/Pattern — Audit Trails.md). No updated_at and no soft deletes:
        // a row is written once and never changed or removed.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('church_id')->constrained()->restrictOnDelete();

            // What changed.
            $table->ulidMorphs('auditable');
            $table->string('event', 30);
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            // Names of the fields that changed, so "every status change" needs no JSON parsing.
            $table->jsonb('changed_fields')->nullable();

            // Who did it. The name is a snapshot, not a join, so renaming a user never rewrites history.
            $table->foreignUlid('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('actor_name')->nullable();

            // Context: the request id ties the row to the application log for the same request.
            $table->string('request_id', 64)->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['church_id', 'created_at']);
            $table->index(['actor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
