<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // A church cannot have two fund types with the same name, whatever the capitals. Deleted rows are left out so a name can be reused.
        DB::statement('CREATE UNIQUE INDEX fund_types_church_id_name_unique ON fund_types (church_id, lower(name)) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS fund_types_church_id_name_unique');
    }
};
