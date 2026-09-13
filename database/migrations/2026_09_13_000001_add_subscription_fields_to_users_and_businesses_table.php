<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'stripe_customer_id')) {
                $table->string('stripe_customer_id')->nullable()->after('remember_token');
            }
        });

        Schema::table('businesses', function (Blueprint $table) {
            if (! Schema::hasColumn('businesses', 'subscription_tier')) {
                $table->string('subscription_tier')->default('free')->after('featured');
                $table->string('billing_cycle')->nullable()->after('subscription_tier');
                $table->string('stripe_customer_id')->nullable()->after('billing_cycle');
                $table->string('stripe_subscription_id')->nullable()->after('stripe_customer_id');
                $table->string('subscription_status')->default('active')->after('stripe_subscription_id');
                $table->timestamp('subscription_ends_at')->nullable()->after('subscription_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'stripe_customer_id')) {
                $table->dropColumn(['stripe_customer_id']);
            }
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn([
                'subscription_tier',
                'billing_cycle',
                'stripe_customer_id',
                'stripe_subscription_id',
                'subscription_status',
                'subscription_ends_at',
            ]);
        });
    }
};
