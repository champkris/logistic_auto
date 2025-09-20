<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn([
                'vsl_payment_status',
                'final_status',
                'shipment_number',
                'port_of_discharge',
                'status',
                'vessel_code',
                'do_pickup_date',
                'container_arrival',
                'berth_location',
                'customs_entry',
                'total_cost',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('vsl_payment_status')->nullable();
            $table->string('final_status')->nullable();
            $table->string('shipment_number');
            $table->string('port_of_discharge');
            $table->string('status')->default('new');
            $table->string('vessel_code')->nullable();
            $table->timestamp('do_pickup_date')->nullable();
            $table->string('container_arrival')->nullable();
            $table->string('berth_location')->nullable();
            $table->string('customs_entry')->nullable();
            $table->decimal('total_cost', 10, 2)->nullable();
        });
    }
};