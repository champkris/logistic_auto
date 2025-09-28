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
        Schema::create('eta_check_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->onDelete('cascade');
            $table->string('terminal')->nullable(); // Which terminal was checked
            $table->string('vessel_name')->nullable();
            $table->string('voyage_code')->nullable();
            $table->datetime('scraped_eta')->nullable(); // ETA found by bot
            $table->datetime('shipment_eta_at_time')->nullable(); // Shipment's planned ETA at time of check
            $table->enum('tracking_status', ['on_track', 'delay', 'not_found']); // Result of comparison
            $table->boolean('vessel_found')->default(false);
            $table->boolean('voyage_found')->default(false);
            $table->text('raw_response')->nullable(); // Full response from tracking service
            $table->text('error_message')->nullable(); // Error if check failed
            $table->integer('initiated_by')->nullable(); // User ID who triggered the check
            $table->timestamps();

            // Indexes for performance
            $table->index(['shipment_id', 'created_at']);
            $table->index('tracking_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eta_check_logs');
    }
};
