<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Staff sign in with email and password (D-054). `password` is the hash and stays empty until the person sets one
 * through the reset flow; `email_verified_at` is stamped by the first emailed code that works. The email is the
 * identity, so it is unique per church whatever its capitals, and a deleted staff member frees theirs. It stays
 * nullable in the database for now: no account may lack one once every account has been given one, which is a later,
 * separate migration (never add a NOT NULL column in one step to a table that has rows).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable();
            $table->timestamp('email_verified_at')->nullable();
        });

        DB::statement('CREATE UNIQUE INDEX users_church_id_email_unique ON users (church_id, lower(email)) WHERE deleted_at IS NULL AND email IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_church_id_email_unique');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['password', 'email_verified_at']);
        });
    }
};
