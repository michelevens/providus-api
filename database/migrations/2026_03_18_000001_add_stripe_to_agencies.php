<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable()->after('plan_tier');
            $table->string('stripe_subscription_id')->nullable()->after('stripe_customer_id');
            $table->string('stripe_price_id')->nullable()->after('stripe_subscription_id');
            $table->string('subscription_status')->default('trialing')->after('stripe_price_id');
            $table->timestamp('trial_ends_at')->nullable()->after('subscription_status');
            $table->timestamp('subscription_ends_at')->nullable()->after('trial_ends_at');
            $table->index('stripe_customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_customer_id', 'stripe_subscription_id', 'stripe_price_id',
                'subscription_status', 'trial_ends_at', 'subscription_ends_at',
            ]);
        });
    }
};
