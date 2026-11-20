<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            // When check-in was last opened. A WhatsApp CHECKIN with no code goes to the service opened first (QA-05 TC-05-13).
            $table->timestamp('checkin_opened_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('checkin_opened_at');
        });
    }
};
