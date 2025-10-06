<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE shipments MODIFY COLUMN tracking_status ENUM('on_track', 'early', 'delay', 'departed', 'not_found') DEFAULT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE shipments MODIFY COLUMN tracking_status ENUM('on_track', 'delay', 'not_found') DEFAULT NULL");
    }
};
