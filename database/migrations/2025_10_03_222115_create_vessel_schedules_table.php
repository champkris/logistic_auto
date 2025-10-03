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
        Schema::create('vessel_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('vessel_name')->index();
            $table->string('voyage_code')->nullable()->index();
            $table->string('port_terminal', 50)->index(); // e.g., C1, C2, B5, SIAM, KERRY
            $table->string('berth', 50)->nullable(); // Specific berth location
            $table->dateTime('eta')->nullable()->index(); // Estimated Time of Arrival
            $table->dateTime('etd')->nullable(); // Estimated Time of Departure
            $table->dateTime('cutoff')->nullable(); // Cutoff time for cargo
            $table->dateTime('opengate')->nullable(); // Gate open time
            $table->string('source', 50)->index(); // Which scraper: hutchison, lcb1, lcit, shipmentlink, tips, etc.
            $table->json('raw_data')->nullable(); // Store original scraped data for debugging
            $table->timestamp('scraped_at')->index(); // When was this data scraped
            $table->timestamp('expires_at')->index(); // When should this data be considered stale (24-48h)
            $table->timestamps();

            // Composite index for fast lookups
            $table->index(['vessel_name', 'port_terminal', 'eta']);
            $table->index(['vessel_name', 'voyage_code']);
            $table->index(['scraped_at', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vessel_schedules');
    }
};
