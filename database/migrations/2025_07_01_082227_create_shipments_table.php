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
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('shipment_number')->unique();
            $table->string('consignee');
            $table->string('hbl_number')->nullable();
            $table->string('mbl_number')->nullable();
            $table->string('invoice_number')->nullable();
            $table->foreignId('vessel_id')->constrained('vessels')->onDelete('cascade');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->string('port_of_discharge');
            $table->enum('status', [
                'new', 
                'planning', 
                'documents_preparation', 
                'customs_clearance', 
                'ready_for_delivery',
                'in_transit', 
                'delivered',
                'completed'
            ]);
            $table->date('planned_delivery_date')->nullable();
            $table->date('actual_delivery_date')->nullable();
            $table->decimal('total_cost', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->json('cargo_details')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'planned_delivery_date']);
            $table->index(['customer_id', 'status']);
            $table->index('shipment_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
