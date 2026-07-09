<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('cardlink_bill_id')->nullable();
            $table->string('cardlink_payment_id')->nullable();
            $table->text('pay_url')->nullable();
            $table->unsignedBigInteger('amount_kopecks');
            $table->enum('status', ['pending', 'success', 'fail', 'refunded'])->default('pending');
            $table->enum('intent', ['wallet_topup', 'subscription_purchase'])->default('wallet_topup');
            $table->unsignedBigInteger('intent_subscription_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index('cardlink_bill_id');
            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'status']);
            $table->index('intent_subscription_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
