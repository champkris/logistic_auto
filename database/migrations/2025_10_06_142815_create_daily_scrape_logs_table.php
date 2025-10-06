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
        Schema::create('daily_scrape_logs', function (Blueprint $table) {
            $table->id();
            $table->string('terminal'); // hutchison, tips, esco, lcit
            $table->json('ports_scraped'); // Array of port codes scraped
            $table->integer('vessels_found')->default(0);
            $table->integer('schedules_created')->default(0);
            $table->integer('schedules_updated')->default(0);
            $table->string('status'); // success, failed, partial
            $table->text('error_message')->nullable();
            $table->integer('duration_seconds')->nullable(); // How long the scrape took
            $table->timestamps();

            // Indexes
            $table->index(['terminal', 'created_at']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_scrape_logs');
    }
};
