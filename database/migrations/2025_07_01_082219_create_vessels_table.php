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
        Schema::create('vessels', function (Blueprint $table) {
            $table->id();
            $table->string('vessel_name');
            $table->string('voyage_number')->nullable();
            $table->datetime('eta')->nullable();
            $table->datetime('actual_arrival')->nullable();
            $table->string('port');
            $table->enum('status', ['scheduled', 'arrived', 'departed', 'delayed']);
            $table->string('imo_number')->nullable();
            $table->string('agent')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['vessel_name', 'voyage_number']);
            $table->index(['port', 'status']);
            $table->index('eta');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vessels');
    }
};
