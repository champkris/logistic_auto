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
            // Drop the FCL field
            $table->dropColumn('fcl_type');

            // Add new quantity fields
            $table->decimal('quantity_number', 10, 2)->nullable()->after('weight_kgm');
            $table->string('quantity_unit')->nullable()->after('quantity_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            // Re-add the FCL field
            $table->string('fcl_type')->nullable()->after('weight_kgm');

            // Drop the quantity fields
            $table->dropColumn(['quantity_number', 'quantity_unit']);
        });
    }
};