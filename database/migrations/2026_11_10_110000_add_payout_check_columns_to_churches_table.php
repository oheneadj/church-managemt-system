<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A bank payout account needs the bank's code, and a payout account that the provider could not name needs its reason kept so
 * the church can see it (D-069 step d2, story 4.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('churches', function (Blueprint $table): void {
            $table->string('payout_bank_code', 20)->nullable()->after('payout_network');
            $table->string('payout_check_error', 255)->nullable()->after('payout_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('churches', function (Blueprint $table): void {
            $table->dropColumn(['payout_bank_code', 'payout_check_error']);
        });
    }
};
