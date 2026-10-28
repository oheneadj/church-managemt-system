<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('church_id')->constrained()->restrictOnDelete();
            $table->string('channel', 30);
            $table->string('recipient_type', 30);
            $table->ulid('recipient_id')->nullable();
            $table->string('recipient_phone', 20)->nullable();
            $table->string('recipient_email')->nullable();
            $table->string('message_type', 100);
            $table->text('content');
            $table->string('status', 30)->default('queued');
            $table->string('provider_reference')->nullable();
            $table->jsonb('provider_response')->nullable();
            $table->decimal('cost_units', 8, 4)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['church_id', 'message_type', 'created_at']);
            $table->index(['church_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_logs');
    }
};
