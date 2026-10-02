<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->renameColumn('stripe_customer_id', 'stripe_id');
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('stripe_subscription_id');
            $table->index('stripe_id');
            $table->string('pm_type')->nullable();
            $table->string('pm_last_four', 4)->nullable();
            $table->timestamp('trial_ends_at')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('stripe_customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable();
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->dropIndex(['stripe_id']);
            $table->dropColumn(['pm_type', 'pm_last_four', 'trial_ends_at']);
            $table->string('stripe_subscription_id')->nullable();
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->renameColumn('stripe_id', 'stripe_customer_id');
        });
    }
};
