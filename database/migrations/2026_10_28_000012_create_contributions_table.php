<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contributions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('church_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('member_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUlid('service_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUlid('fund_type_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('pledge_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->decimal('processing_fee', 12, 2)->nullable();
            $table->decimal('total_paid', 12, 2)->nullable();
            $table->string('payment_method', 30);
            $table->string('paystack_reference', 100)->nullable()->unique();
            $table->string('paystack_status', 50)->nullable();
            $table->string('payperlez_reference', 100)->nullable()->unique();
            $table->string('ussd_reference', 100)->nullable()->unique();
            $table->boolean('is_voided')->default(false);
            $table->timestamp('voided_at')->nullable();
            $table->foreignUlid('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('void_reason')->nullable();
            $table->foreignUlid('recorded_by')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('contributed_at');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['church_id', 'member_id']);
            $table->index(['church_id', 'service_id']);
            $table->index(['church_id', 'contributed_at']);
            $table->index(['church_id', 'fund_type_id', 'contributed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contributions');
    }
};
