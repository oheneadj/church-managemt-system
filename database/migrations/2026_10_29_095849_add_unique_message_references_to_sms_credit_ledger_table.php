<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // A message is charged once and refunded at most once (agreement section 11, layer 4). The ledger line points at
        // the message log row, and these two indexes are what hold when two attempts race.
        DB::statement("CREATE UNIQUE INDEX sms_credit_ledger_one_consumption ON sms_credit_ledger (reference_id) WHERE type = 'consumption' AND reference_id IS NOT NULL");
        DB::statement("CREATE UNIQUE INDEX sms_credit_ledger_one_refund ON sms_credit_ledger (reference_id) WHERE type = 'refund' AND reference_id IS NOT NULL");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS sms_credit_ledger_one_refund');
        DB::statement('DROP INDEX IF EXISTS sms_credit_ledger_one_consumption');
    }
};
