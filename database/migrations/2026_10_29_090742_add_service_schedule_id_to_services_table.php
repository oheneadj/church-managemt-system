<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            // The schedule that made this service. Null for a service made by hand.
            $table->foreignUlid('service_schedule_id')->nullable()->after('church_id')->constrained('service_schedules')->restrictOnDelete();
        });

        // One service per schedule per date, ever. This index deliberately counts cancelled (soft-deleted) services too,
        // so a schedule never makes a date again that someone cancelled. Two schedules, or a service made by hand, may
        // still share a date (QA-05 TC-05-13).
        DB::statement('CREATE UNIQUE INDEX services_schedule_date_unique ON services (service_schedule_id, service_date) WHERE service_schedule_id IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS services_schedule_date_unique');

        Schema::table('services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_schedule_id');
        });
    }
};
