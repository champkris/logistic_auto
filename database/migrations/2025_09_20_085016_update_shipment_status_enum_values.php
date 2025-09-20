<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, convert ENUM columns to VARCHAR to allow data updates
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('customs_clearance_status_temp')->nullable()->after('customs_clearance_status');
            $table->string('overtime_status_temp')->nullable()->after('overtime_status');
            $table->string('do_status_temp')->nullable()->after('do_status');
        });

        // Copy data to temp columns with new values
        DB::table('shipments')->update([
            'customs_clearance_status_temp' => DB::raw("CASE
                WHEN customs_clearance_status = 'no_clearance' THEN 'pending'
                WHEN customs_clearance_status = 'has_clearance' THEN 'received'
                ELSE 'pending'
            END"),
            'overtime_status_temp' => DB::raw("CASE
                WHEN overtime_status = 'no_ot' THEN 'none'
                WHEN overtime_status = 'ot_1_period' THEN 'ot1'
                WHEN overtime_status = 'ot_2_periods' THEN 'ot2'
                ELSE 'none'
            END"),
            'do_status_temp' => DB::raw("CASE
                WHEN do_status = 'not_received' THEN 'pending'
                WHEN do_status = 'received' THEN 'received'
                ELSE 'pending'
            END")
        ]);

        // Drop old ENUM columns
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['customs_clearance_status', 'overtime_status', 'do_status']);
        });

        // Add new ENUM columns with updated values
        Schema::table('shipments', function (Blueprint $table) {
            $table->enum('customs_clearance_status', ['pending', 'received', 'processing'])->default('pending')->after('joint_pickup');
            $table->enum('overtime_status', ['none', 'ot1', 'ot2', 'ot3'])->default('none')->after('customs_clearance_status');
            $table->enum('do_status', ['pending', 'received', 'processing'])->default('pending')->after('overtime_status');
        });

        // Copy data back from temp columns
        DB::table('shipments')->update([
            'customs_clearance_status' => DB::raw('customs_clearance_status_temp'),
            'overtime_status' => DB::raw('overtime_status_temp'),
            'do_status' => DB::raw('do_status_temp')
        ]);

        // Drop temp columns
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['customs_clearance_status_temp', 'overtime_status_temp', 'do_status_temp']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert data back to old enum values
        DB::table('shipments')->where('customs_clearance_status', 'pending')->update(['customs_clearance_status' => 'no_clearance']);
        DB::table('shipments')->where('customs_clearance_status', 'received')->update(['customs_clearance_status' => 'has_clearance']);
        DB::table('shipments')->where('customs_clearance_status', 'processing')->update(['customs_clearance_status' => 'no_clearance']);

        DB::table('shipments')->where('overtime_status', 'none')->update(['overtime_status' => 'no_ot']);
        DB::table('shipments')->where('overtime_status', 'ot1')->update(['overtime_status' => 'ot_1_period']);
        DB::table('shipments')->where('overtime_status', 'ot2')->update(['overtime_status' => 'ot_2_periods']);
        DB::table('shipments')->where('overtime_status', 'ot3')->update(['overtime_status' => 'ot_2_periods']);

        DB::table('shipments')->where('do_status', 'pending')->update(['do_status' => 'not_received']);
        DB::table('shipments')->where('do_status', 'processing')->update(['do_status' => 'not_received']);

        // Recreate columns with old enum values
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['customs_clearance_status', 'overtime_status', 'do_status']);
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->enum('customs_clearance_status', ['no_clearance', 'has_clearance'])->default('no_clearance')->after('joint_pickup');
            $table->enum('overtime_status', ['no_ot', 'ot_1_period', 'ot_2_periods'])->default('no_ot')->after('customs_clearance_status');
            $table->enum('do_status', ['not_received', 'received'])->default('not_received')->after('overtime_status');
        });
    }
};