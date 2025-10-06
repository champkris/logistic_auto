<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('eta_check_schedules', function (Blueprint $table) {
            $table->string('schedule_type')->default('eta_check')->after('name');
            // schedule_type values: 'vessel_scrape' or 'eta_check'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('eta_check_schedules', function (Blueprint $table) {
            $table->dropColumn('schedule_type');
        });
    }
};
