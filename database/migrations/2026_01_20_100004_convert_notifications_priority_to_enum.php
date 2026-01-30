<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Convert notifications.priority from string to enum
     */
    public function up(): void
    {
        // First, ensure all existing values are valid
        DB::statement("UPDATE notifications SET priority = 'normal' WHERE priority NOT IN ('low', 'normal', 'high', 'urgent') OR priority IS NULL OR priority = ''");
        
        Schema::table('notifications', function (Blueprint $table) {
            // Drop existing index
            $table->dropIndex(['priority']);
        });
        
        // Change column type to enum
        DB::statement("ALTER TABLE notifications MODIFY COLUMN priority ENUM('low', 'normal', 'high', 'urgent') NOT NULL DEFAULT 'normal'");
        
        Schema::table('notifications', function (Blueprint $table) {
            // Recreate index
            $table->index('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['priority']);
        });
        
        DB::statement("ALTER TABLE notifications MODIFY COLUMN priority VARCHAR(20) NOT NULL DEFAULT 'normal'");
        
        Schema::table('notifications', function (Blueprint $table) {
            $table->index('priority');
        });
    }
};

