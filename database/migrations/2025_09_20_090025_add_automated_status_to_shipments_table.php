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
        Schema::table('shipments', function (Blueprint $table) {
            $table->enum('status', ['in-progress', 'completed'])->default('in-progress')->after('do_status');
        });

        // Update existing shipments based on their customs and DO status
        DB::table('shipments')
            ->where('customs_clearance_status', 'received')
            ->where('do_status', 'received')
            ->update(['status' => 'completed']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};