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
        // Drop and recreate the table with correct schema
        Schema::dropIfExists('siam_com_chatbot_eta_requests');
        
        Schema::create('siam_com_chatbot_eta_requests', function (Blueprint $table) {
            $table->id();
            $table->string('group_id')->index(); // Index but NOT unique
            $table->string('vessel_name');
            $table->string('voyage_code');
            $table->dateTime('last_known_eta')->nullable();
            $table->string('status')->default('READY');
            $table->dateTime('last_asked_at')->nullable();
            $table->integer('attempts')->default(0);
            $table->json('conversation_history')->nullable();
            $table->timestamps();
            
            // CRITICAL: Composite unique index allows multiple ships per group
            // Same group can have different ships, but same ship (vessel+voyage) in group must be unique
            $table->unique(['group_id', 'vessel_name', 'voyage_code'], 'unique_vessel_per_group');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siam_com_chatbot_eta_requests');
    }
};
