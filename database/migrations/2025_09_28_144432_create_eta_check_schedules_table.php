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
        Schema::create('eta_check_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // User-friendly name like "Morning Check"
            $table->time('check_time'); // Time of day to run (e.g., 08:30:00)
            $table->boolean('is_active')->default(true);
            $table->json('days_of_week')->nullable(); // [1,2,3,4,5] for Mon-Fri, null for daily
            $table->text('description')->nullable(); // Optional description
            $table->datetime('last_run_at')->nullable(); // Track when it last executed
            $table->datetime('next_run_at')->nullable(); // Calculated next run time
            $table->integer('created_by')->nullable(); // User who created this schedule
            $table->timestamps();

            // Indexes for performance
            $table->index(['is_active', 'check_time']);
            $table->index('next_run_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eta_check_schedules');
    }
};
