<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('families', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('church_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->ulid('head_member_id')->nullable(); // FK added in the members migration (circular)
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('church_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('families');
    }
};
