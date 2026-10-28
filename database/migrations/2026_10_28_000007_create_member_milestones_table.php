<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_milestones', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('church_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('member_id')->constrained()->restrictOnDelete();
            $table->string('type', 30);
            $table->date('occurred_at');
            $table->string('officiant')->nullable();
            $table->string('cohort', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignUlid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['church_id', 'member_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_milestones');
    }
};
