<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The platform owner's own accounts (D-069). They belong to no church, so there is no church_id and no tenant scope. An
     * account is switched off with is_active, never deleted.
     */
    public function up(): void
    {
        Schema::create('platform_admins', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            // Always stored in lowercase, so one address is one account.
            $table->string('email')->unique();
            // Hashed. Set from the command line, never through the app.
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_admins');
    }
};
