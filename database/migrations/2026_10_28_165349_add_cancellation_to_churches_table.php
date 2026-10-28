<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A church that cancels keeps working for a grace period so it can take its data with it, then its personal
     * data is purged (docs/05). cancelled_at starts the grace period; purged_at records that the purge happened.
     * Both are empty for a church in good standing, so the change is safe for the code that is already deployed.
     */
    public function up(): void
    {
        Schema::table('churches', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('is_active');
            $table->timestamp('purged_at')->nullable()->after('cancelled_at');
            $table->index('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('churches', function (Blueprint $table) {
            $table->dropIndex(['cancelled_at']);
            $table->dropColumn(['cancelled_at', 'purged_at']);
        });
    }
};
