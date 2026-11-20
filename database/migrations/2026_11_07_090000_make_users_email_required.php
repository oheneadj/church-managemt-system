<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every staff account must have an email, because the email is how staff sign in (D-054). The column was nullable while
 * the sign-in still used phone numbers; every way of creating a staff member now supplies an email, so the database can
 * insist on it. An account with no email cannot sign in and cannot be given one by the app, so the migration stops with
 * a clear message instead of guessing an address. Nothing is in production (D-054); on a local database, give those
 * accounts an email or run `php artisan migrate:fresh --seed`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $without = DB::table('users')->whereNull('email')->count();

        if ($without > 0) {
            throw new RuntimeException("{$without} staff account(s) have no email. Give each one an email, or on a local database run migrate:fresh --seed, then migrate again.");
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }
};
