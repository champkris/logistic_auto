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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->onDelete('cascade');
            $table->enum('type', [
                'do', 
                'customs_declaration', 
                'permit', 
                'mill_test', 
                'bill_of_lading',
                'invoice',
                'packing_list',
                'certificate',
                'other'
            ]);
            $table->string('document_name');
            $table->string('file_path')->nullable();
            $table->enum('status', ['pending', 'received', 'approved', 'rejected', 'expired']);
            $table->decimal('cost', 10, 2)->nullable();
            $table->date('due_date')->nullable();
            $table->date('received_date')->nullable();
            $table->string('issued_by')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            
            $table->index(['shipment_id', 'type']);
            $table->index(['status', 'due_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
