<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contributions', function (Blueprint $table) {
            // The fund's name when the gift was recorded (agreement section 10, snapshots). A fund can be renamed later;
            // the gift keeps saying what it was given to. Nullable now so existing rows survive: the batched command
            // contributions:backfill-fund-names fills them, then the next migration makes the column required.
            $table->string('fund_name', 100)->nullable()->after('fund_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('contributions', function (Blueprint $table) {
            $table->dropColumn('fund_name');
        });
    }
};
