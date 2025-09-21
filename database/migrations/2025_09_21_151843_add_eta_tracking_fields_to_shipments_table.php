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
            $table->timestamp('last_eta_check_date')->nullable()->after('planned_delivery_date');
            $table->timestamp('bot_received_eta_date')->nullable()->after('last_eta_check_date');
            $table->enum('tracking_status', ['on_track', 'delay'])->nullable()->after('bot_received_eta_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['last_eta_check_date', 'bot_received_eta_date', 'tracking_status']);
        });
    }
};