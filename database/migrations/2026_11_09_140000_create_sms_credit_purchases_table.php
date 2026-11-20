<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A church's purchase of an SMS credit package (D-069, story 5.4). The row is made `pending` when the payment starts and its id
     * is the reference sent to the provider (as a gift's id is, D-053). It moves to `completed` (the credits are added, once) or
     * `failed` when the provider confirms. The credits and the price are copied from the package when it is bought, so changing
     * a package later never changes what a church already paid for.
     */
    public function up(): void
    {
        Schema::create('sms_credit_purchases', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('church_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('sms_package_id')->constrained('sms_packages');
            // The staff member who started the purchase.
            $table->foreignUlid('user_id')->constrained('users');
            $table->unsignedInteger('credits');
            // What the church pays for the package, in GHS (D-017). The provider's own fee is on top and is kept in provider_fee.
            $table->decimal('amount', 12, 2);
            $table->string('network', 30);
            // The number the payment prompt goes to. Personal data, so it is stored encrypted.
            $table->text('phone');
            $table->string('status', 30)->default('pending');
            // The provider's own id for the payment and what it charged on top, known once the provider answers.
            $table->string('provider_reference', 100)->nullable();
            $table->decimal('provider_fee', 12, 2)->nullable();
            $table->string('failure_reason', 255)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['church_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        // A purchase adds its credits once (agreement section 11, layer 4). The ledger line points at the purchase, and this index is
        // what holds when two checks of the same payment race.
        DB::statement("CREATE UNIQUE INDEX sms_credit_ledger_one_topup ON sms_credit_ledger (reference_id) WHERE type = 'topup' AND reference_id IS NOT NULL");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS sms_credit_ledger_one_topup');
        Schema::dropIfExists('sms_credit_purchases');
    }
};
