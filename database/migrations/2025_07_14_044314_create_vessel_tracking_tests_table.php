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
        Schema::create('vessel_tracking_tests', function (Blueprint $table) {
            $table->id();
            $table->string('endpoint_key');
            $table->string('endpoint_name');
            $table->text('endpoint_url');
            $table->string('status');
            $table->integer('status_code')->nullable();
            $table->decimal('response_time', 10, 2)->nullable();
            $table->string('content_type')->nullable();
            $table->integer('content_length')->nullable();
            $table->boolean('accessible')->default(false);
            $table->boolean('has_vessel_data')->default(false);
            $table->json('content_analysis')->nullable();
            $table->json('automation_potential')->nullable();
            $table->text('error_message')->nullable();
            $table->longText('raw_response')->nullable();
            $table->timestamp('tested_at');
            $table->timestamps();
            
            $table->index(['endpoint_key', 'tested_at']);
            $table->index('status');
            $table->index('has_vessel_data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vessel_tracking_tests');
    }
};