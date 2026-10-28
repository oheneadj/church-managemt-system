<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disbursements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('church_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('contribution_id')->unique()->constrained()->restrictOnDelete(); // one payout per contribution
            $table->decimal('gross_amount', 12, 2);
            $table->decimal('platform_fee', 12, 2);
            $table->decimal('requested_amount', 12, 2);
            $table->decimal('provider_fee', 12, 2)->nullable();
            $table->decimal('net_amount', 12, 2)->nullable();
            $table->string('status', 30)->default('pending');
            $table->string('provider', 30);
            $table->string('provider_reference', 100)->nullable()->unique();
            $table->integer('attempts')->default(0);
            $table->text('failure_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['church_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disbursements');
    }
};
