<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('provider', 32)->nullable()->after('user_id')->index();
            $table->string('external_id')->nullable()->after('provider')->index();
            $table->string('provider_operation_id')->nullable()->after('external_id');
        });

        // Backfill: всё, что было до мультипровайдерности, — cardlink.
        DB::table('payments')->whereNull('provider')->update(['provider' => 'cardlink']);
        DB::statement('UPDATE payments SET external_id = cardlink_bill_id WHERE external_id IS NULL AND cardlink_bill_id IS NOT NULL');
        DB::statement('UPDATE payments SET provider_operation_id = cardlink_payment_id WHERE provider_operation_id IS NULL AND cardlink_payment_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['provider', 'external_id', 'provider_operation_id']);
        });
    }
};
