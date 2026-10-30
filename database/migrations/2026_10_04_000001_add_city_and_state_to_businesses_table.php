<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('city')->nullable()->after('location');
            $table->string('state', 2)->nullable()->after('city');
        });

        // Backfill from "City, ST" where the existing value matches that shape.
        DB::table('businesses')->orderBy('id')->each(function ($business) {
            if (preg_match('/^(.+?),\s*([A-Za-z]{2})$/', trim($business->location), $m)) {
                DB::table('businesses')->where('id', $business->id)->update([
                    'city' => trim($m[1]),
                    'state' => strtoupper($m[2]),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['city', 'state']);
        });
    }
};
