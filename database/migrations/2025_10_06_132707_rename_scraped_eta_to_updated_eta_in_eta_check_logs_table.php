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
        Schema::table('eta_check_logs', function (Blueprint $table) {
            $table->renameColumn('scraped_eta', 'updated_eta');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('eta_check_logs', function (Blueprint $table) {
            $table->renameColumn('updated_eta', 'scraped_eta');
        });
    }
};
