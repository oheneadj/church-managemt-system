<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The SMS credit bundles a church can buy (D-069). They belong to the platform, not to a church, so there is no church_id.
     * A package is switched off with is_active, never deleted, because purchases will point at it.
     */
    public function up(): void
    {
        Schema::create('sms_packages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 100);
            // How many credits one purchase adds. One credit is one part of a text (D-051).
            $table->unsignedInteger('credits');
            // What the church pays, in GHS (D-017). Exact: never a float.
            $table->decimal('price', 12, 2);
            $table->boolean('is_active')->default(true);
            // The order churches see the packages in, lowest first.
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        // Names are unique whatever their capitals, held by the database as well as by the form's rule.
        DB::statement('create unique index sms_packages_name_unique on sms_packages (lower(name))');
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_packages');
    }
};
