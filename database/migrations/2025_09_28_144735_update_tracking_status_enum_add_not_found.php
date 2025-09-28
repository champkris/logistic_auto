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
        DB::statement("ALTER TABLE shipments MODIFY COLUMN tracking_status ENUM('on_track', 'delay', 'not_found') NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE shipments MODIFY COLUMN tracking_status ENUM('on_track', 'delay') NULL");
    }
};
