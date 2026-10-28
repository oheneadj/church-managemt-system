<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitor_records', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('church_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('phone', 20)->nullable();
            $table->string('how_heard')->nullable();
            $table->foreignUlid('first_visit_service_id')->nullable()->constrained('services')->restrictOnDelete();
            $table->date('first_visit_date');
            $table->integer('visit_count')->default(1);
            $table->date('last_visit_date')->nullable();
            $table->string('follow_up_status', 30)->default('pending');
            $table->foreignUlid('converted_member_id')->nullable()->constrained('members')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->foreignUlid('assigned_to')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('church_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitor_records');
    }
};
