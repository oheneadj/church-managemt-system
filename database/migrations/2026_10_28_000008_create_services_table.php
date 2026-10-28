<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('church_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('type', 30);
            $table->string('checkin_code', 10)->nullable();
            $table->date('service_date');
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->boolean('is_open')->default(false);
            $table->integer('headcount_override')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUlid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['church_id', 'service_date']);
        });

        DB::statement('CREATE UNIQUE INDEX services_church_id_checkin_code_unique ON services (church_id, checkin_code) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
