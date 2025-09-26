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
        // Clean up vessel name columns by standardizing data

        // Step 1: Copy vessel_name to name where name is empty
        DB::table('vessels')
            ->whereNull('name')
            ->orWhere('name', '')
            ->update([
                'name' => DB::raw('vessel_name')
            ]);

        // Step 2: Copy name to vessel_name where vessel_name is empty (just in case)
        DB::table('vessels')
            ->whereNull('vessel_name')
            ->orWhere('vessel_name', '')
            ->update([
                'vessel_name' => DB::raw('name')
            ]);

        // Step 3: Set full_vessel_name to be the same as name for consistency
        DB::table('vessels')->update([
            'full_vessel_name' => DB::raw('name')
        ]);

        // Step 4: Ensure no null values in critical name fields
        DB::table('vessels')
            ->whereNull('name')
            ->update(['name' => 'Unknown Vessel']);

        DB::table('vessels')
            ->whereNull('vessel_name')
            ->update(['vessel_name' => 'Unknown Vessel']);

        DB::table('vessels')
            ->whereNull('full_vessel_name')
            ->update(['full_vessel_name' => DB::raw('name')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration doesn't need to be reversed as it's data cleanup
        // The original data structure is preserved
    }
};