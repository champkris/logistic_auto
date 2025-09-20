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
            // Add pickup_location field
            $table->string('pickup_location')->nullable()->after('shipping_line');
        });

        // Migrate data from shipping_line to pickup_location
        DB::table('shipments')->update([
            'pickup_location' => DB::raw('shipping_line')
        ]);

        Schema::table('shipments', function (Blueprint $table) {
            // Remove shipping_line column
            $table->dropColumn('shipping_line');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            // Re-add shipping_line field
            $table->string('shipping_line')->nullable()->after('cargo_details');
        });

        // Migrate data back from pickup_location to shipping_line
        DB::table('shipments')->update([
            'shipping_line' => DB::raw('pickup_location')
        ]);

        Schema::table('shipments', function (Blueprint $table) {
            // Remove pickup_location column
            $table->dropColumn('pickup_location');
        });
    }
};