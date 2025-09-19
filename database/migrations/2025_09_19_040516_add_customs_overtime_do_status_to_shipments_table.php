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
        Schema::table('shipments', function (Blueprint $table) {
            $table->enum('customs_clearance_status', ['no_clearance', 'has_clearance'])->default('no_clearance')->after('customs_entry');
            $table->enum('overtime_status', ['no_ot', 'ot_1_period', 'ot_2_periods'])->default('no_ot')->after('customs_clearance_status');
            $table->enum('do_status', ['not_received', 'received'])->default('not_received')->after('do_pickup_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['customs_clearance_status', 'overtime_status', 'do_status']);
        });
    }
};
