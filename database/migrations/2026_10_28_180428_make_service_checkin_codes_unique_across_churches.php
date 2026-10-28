<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A check-in code now identifies a service across all churches (a member texts "CHECKIN GRA7F" to one shared
     * WhatsApp number), so it must be unique everywhere and not only inside one church. Only live rows count: a
     * deleted service frees its code. The earlier per-church rule is implied by this one.
     */
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS services_church_id_checkin_code_unique');
        DB::statement('CREATE UNIQUE INDEX services_checkin_code_unique ON services (checkin_code) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS services_checkin_code_unique');
        DB::statement('CREATE UNIQUE INDEX services_church_id_checkin_code_unique ON services (church_id, checkin_code) WHERE deleted_at IS NULL');
    }
};
