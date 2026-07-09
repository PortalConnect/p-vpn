<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->unsignedInteger('trial_days')->default(0)->after('price_kopecks');
            $table->unsignedInteger('grace_days')->nullable()->after('trial_days');
            $table->unsignedInteger('signup_fee_kopecks')->default(0)->after('grace_days');
        });

        // Лимитируемые возможности плана: value NULL = безлимит.
        Schema::create('subscription_plan_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('subscription_plans')->cascadeOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->unsignedInteger('value')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['plan_id', 'slug']);
        });

        // Учёт использования фич в рамках подписки.
        Schema::create('subscription_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->string('feature_slug');
            $table->unsignedInteger('used')->default(0);
            $table->timestamps();
            $table->unique(['subscription_id', 'feature_slug']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('tag')->default('main')->after('plan_id')->index();
            $table->timestamp('trial_ends_at')->nullable()->after('ends_at');
            $table->timestamp('canceled_at')->nullable()->after('trial_ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['tag', 'trial_ends_at', 'canceled_at']);
        });
        Schema::dropIfExists('subscription_usage');
        Schema::dropIfExists('subscription_plan_features');
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn(['trial_days', 'grace_days', 'signup_fee_kopecks']);
        });
    }
};
