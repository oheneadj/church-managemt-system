<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('church_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('service_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('member_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('visitor_name')->nullable();
            $table->string('method', 30);
            $table->timestamp('checked_in_at');
            $table->timestamps();
            $table->softDeletes();

            // Prevents a double check-in. NULL member_id (walk-in visitors) never collides.
            $table->unique(['church_id', 'service_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
