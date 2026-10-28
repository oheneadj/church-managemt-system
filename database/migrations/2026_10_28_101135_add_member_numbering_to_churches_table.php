<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('churches', function (Blueprint $table) {
            // The letters in front of every member number, typed by the admin at onboarding (D-030). Null until set.
            $table->string('member_number_prefix', 10)->nullable()->after('timezone');
            // The last number handed out. Only ever incremented under a row lock, so a number is never reused,
            // even when the member that held it is later deleted.
            $table->unsignedInteger('member_number_seq')->default(0)->after('member_number_prefix');
        });
    }

    public function down(): void
    {
        Schema::table('churches', function (Blueprint $table) {
            $table->dropColumn(['member_number_prefix', 'member_number_seq']);
        });
    }
};
