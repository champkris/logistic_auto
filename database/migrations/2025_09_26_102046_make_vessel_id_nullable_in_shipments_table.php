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
            // Make vessel_id nullable to allow shipments without assigned vessels
            $table->unsignedBigInteger('vessel_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            // Revert vessel_id back to non-nullable
            $table->unsignedBigInteger('vessel_id')->nullable(false)->change();
        });
    }
};