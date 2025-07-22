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
        Schema::table('vessels', function (Blueprint $table) {
            // Add name column for API compatibility (copy from vessel_name) - only if it doesn't exist
            if (!Schema::hasColumn('vessels', 'name')) {
                $table->string('name')->nullable()->after('vessel_name');
            }
            
            // Add automation tracking fields - only if they don't exist
            if (!Schema::hasColumn('vessels', 'metadata')) {
                $table->json('metadata')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('vessels', 'last_scraped_at')) {
                $table->timestamp('last_scraped_at')->nullable()->after('metadata');
            }
            if (!Schema::hasColumn('vessels', 'scraping_source')) {
                $table->string('scraping_source')->nullable()->after('last_scraped_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vessels', function (Blueprint $table) {
            $columnsToCheck = ['name', 'metadata', 'last_scraped_at', 'scraping_source'];
            $columnsToDrop = [];
            
            foreach ($columnsToCheck as $column) {
                if (Schema::hasColumn('vessels', $column)) {
                    $columnsToDrop[] = $column;
                }
            }
            
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};