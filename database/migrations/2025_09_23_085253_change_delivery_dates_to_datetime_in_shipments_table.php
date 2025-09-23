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
            $table->datetime('planned_delivery_date')->nullable()->change();
            $table->datetime('actual_delivery_date')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->date('planned_delivery_date')->nullable()->change();
            $table->date('actual_delivery_date')->nullable()->change();
        });
    }
};