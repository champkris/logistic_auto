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
            // Excel spreadsheet fields
            $table->integer('quantity_days')->nullable()->after('invoice_number'); // จำนวน (DT)
            $table->datetime('do_pickup_date')->nullable()->after('quantity_days'); // ขึ้น DO
            $table->decimal('weight_kgm', 10, 2)->nullable()->after('do_pickup_date'); // KGM
            $table->string('fcl_type')->nullable()->after('weight_kgm'); // FCL
            $table->string('container_arrival')->nullable()->after('fcl_type'); // ตู้เข้า
            $table->string('berth_location')->nullable()->after('port_of_discharge'); // ที่เรือ
            $table->string('joint_pickup')->nullable()->after('berth_location'); // ร่วมขึ้น
            $table->string('customs_entry')->nullable()->after('joint_pickup'); // CE
            $table->string('vessel_loading_status')->nullable()->after('customs_entry'); // ขึ้น VSL พรขจก
            $table->string('thai_status')->nullable()->after('status'); // สถานะ (Thai status from Excel)

            // Additional indexes for performance
            $table->index('do_pickup_date');
            $table->index('fcl_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropIndex(['do_pickup_date']);
            $table->dropIndex(['fcl_type']);

            $table->dropColumn([
                'quantity_days',
                'do_pickup_date',
                'weight_kgm',
                'fcl_type',
                'container_arrival',
                'berth_location',
                'joint_pickup',
                'customs_entry',
                'vessel_loading_status',
                'thai_status'
            ]);
        });
    }
};
