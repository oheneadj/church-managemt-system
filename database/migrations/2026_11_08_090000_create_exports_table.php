<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row for each CSV export a staff member asked for (D-064). It records who asked, what for, the filters used, how it
 * went and where the private file is, so there is a trail of every export of personal data. The file itself is in
 * private storage and is deleted after `config/exports.php` days; the row stays.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exports', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('church_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('type', 30);
            $table->string('status', 30)->default('queued');
            $table->json('filters')->nullable();
            $table->unsignedInteger('row_count')->nullable();
            $table->string('file_path', 500)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('file_deleted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['church_id', 'requested_by', 'created_at']);
            $table->index(['status', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exports');
    }
};
