<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pledges', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('church_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('member_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('fund_type_id')->constrained()->restrictOnDelete();
            $table->string('description');
            $table->decimal('amount_pledged', 12, 2);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->date('target_date');
            $table->string('status', 30)->default('active');
            $table->foreignUlid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['church_id', 'member_id', 'status']);
            $table->index(['church_id', 'target_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pledges');
    }
};
