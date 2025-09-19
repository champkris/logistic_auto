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
            $table->string('voyage')->nullable()->after('vessel_id');
            $table->string('port_terminal')->nullable()->after('port_of_discharge');
            $table->string('transport_type')->nullable()->after('port_terminal');
            $table->string('cs_reference')->nullable()->after('transport_type');
            $table->string('vsl_payment_status')->nullable()->after('cs_reference');
            $table->string('final_status')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn([
                'voyage',
                'port_terminal',
                'transport_type',
                'cs_reference',
                'vsl_payment_status',
                'final_status'
            ]);
        });
    }
};
