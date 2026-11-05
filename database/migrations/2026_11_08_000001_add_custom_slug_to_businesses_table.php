<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            // Masked address (/b/{custom_slug}) that redirects to the business website.
            $table->string('custom_slug', 60)->nullable()->unique()->after('website');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropUnique(['custom_slug']);
            $table->dropColumn('custom_slug');
        });
    }
};
