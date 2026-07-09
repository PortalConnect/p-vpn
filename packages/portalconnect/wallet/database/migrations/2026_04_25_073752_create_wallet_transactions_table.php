<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', [
                'topup',
                'subscription_debit',
                'refund',
                'bonus',
                'manual_credit',
                'manual_debit',
            ]);
            $table->bigInteger('amount_kopecks');
            $table->bigInteger('balance_after_kopecks');
            $table->unsignedBigInteger('related_payment_id')->nullable();
            $table->unsignedBigInteger('related_subscription_id')->nullable();
            $table->string('description', 255)->nullable();
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['wallet_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['type', 'created_at']);
            $table->index('related_payment_id');
            $table->index('related_subscription_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
