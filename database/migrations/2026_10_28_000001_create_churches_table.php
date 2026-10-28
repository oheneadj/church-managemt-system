<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('churches', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug', 100)->unique();
            $table->string('custom_domain')->nullable()->unique();
            $table->string('denomination')->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('logo_url', 500)->nullable();
            $table->string('plan', 30)->default('starter');
            $table->timestamp('plan_expires_at')->nullable();
            $table->string('paystack_subaccount_code', 100)->nullable();
            $table->string('payout_method', 30)->nullable();
            $table->string('payout_network', 30)->nullable();
            $table->text('payout_account_number')->nullable(); // encrypted cast
            $table->string('payout_account_name')->nullable();
            $table->timestamp('payout_verified_at')->nullable();
            $table->integer('sms_credits')->default(0);
            $table->boolean('whatsapp_opted_in')->default(false);
            $table->string('ussd_code', 10)->nullable()->unique();
            $table->string('timezone', 50)->default('Africa/Accra');
            $table->jsonb('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('churches');
    }
};
