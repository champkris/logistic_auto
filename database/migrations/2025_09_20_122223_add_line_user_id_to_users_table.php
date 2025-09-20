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
        Schema::table('users', function (Blueprint $table) {
            $table->string('line_user_id')->nullable()->unique()->after('remember_token');
            $table->string('line_display_name')->nullable()->after('line_user_id');
            $table->string('line_picture_url')->nullable()->after('line_display_name');
            $table->timestamp('line_connected_at')->nullable()->after('line_picture_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['line_user_id', 'line_display_name', 'line_picture_url', 'line_connected_at']);
        });
    }
};
