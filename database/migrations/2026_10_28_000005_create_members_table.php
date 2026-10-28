<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('church_id')->constrained()->restrictOnDelete();
            $table->string('member_number', 20);
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('preferred_name', 100)->nullable();
            $table->string('gender', 30);
            $table->date('date_of_birth')->nullable();
            $table->string('phone', 20);
            $table->string('phone_secondary', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable(); // encrypted cast
            $table->string('nearest_landmark')->nullable();
            $table->string('digital_address', 20)->nullable();
            $table->string('photo_url', 500)->nullable();
            $table->string('national_id_type', 50)->nullable();
            $table->text('national_id_number')->nullable(); // encrypted cast
            $table->string('marital_status', 30)->nullable();
            $table->string('occupation')->nullable();
            $table->string('tribe', 100)->nullable();
            $table->string('language_preference', 50)->nullable();
            $table->string('educational_level', 30)->nullable();
            $table->string('membership_type', 30)->default('full');
            $table->date('membership_date')->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamp('status_changed_at')->nullable();
            $table->text('status_note')->nullable();
            $table->foreignUlid('family_id')->nullable()->constrained('families')->restrictOnDelete();
            $table->string('family_role', 30)->nullable();
            $table->foreignUlid('assigned_pastor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->boolean('whatsapp_opted_in')->default(false);
            $table->boolean('sms_opted_in')->default(true);
            $table->foreignUlid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['church_id', 'status']);
            $table->index(['church_id', 'family_id']);
            $table->index(['church_id', 'last_name', 'first_name']);
        });

        DB::statement('CREATE UNIQUE INDEX members_church_id_phone_unique ON members (church_id, phone) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX members_church_id_member_number_unique ON members (church_id, member_number) WHERE deleted_at IS NULL');

        Schema::table('families', function (Blueprint $table) {
            $table->foreign('head_member_id')->references('id')->on('members')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('families', function (Blueprint $table) {
            $table->dropForeign(['head_member_id']);
        });

        Schema::dropIfExists('members');
    }
};
