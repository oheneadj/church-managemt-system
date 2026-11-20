<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('handled_whatsapp_messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            // Meta's own id for the message. Meta delivers at least once, so the unique index is what stops a repeat being handled twice.
            // There is no church_id: the church is only known after the sender's phone is matched.
            $table->string('message_id', 100)->unique();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('handled_whatsapp_messages');
    }
};
