<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_schedules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('church_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('type', 30);
            // The weekday as text (Weekday enum: sunday ... saturday), so no number needs explaining.
            $table->string('weekday', 10);
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            // Off means no new services are made; the ones already made stay (D-050).
            $table->boolean('is_active')->default(true);
            $table->foreignUlid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['church_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_schedules');
    }
};
