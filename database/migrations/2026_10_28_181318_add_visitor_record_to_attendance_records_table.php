<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Links a walk-in's attendance row to their visitor record, so a visitor is counted once per service even if
     * the form is sent twice. Members and anonymous walk-ins have no visitor record, and a null never collides in the
     * unique index. The column is empty by default, so code that is already deployed keeps working.
     */
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->foreignUlid('visitor_record_id')->nullable()->after('member_id')->constrained('visitor_records')->restrictOnDelete();
            $table->unique(['church_id', 'service_id', 'visitor_record_id']);
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropUnique(['church_id', 'service_id', 'visitor_record_id']);
            $table->dropConstrainedForeignId('visitor_record_id');
        });
    }
};
