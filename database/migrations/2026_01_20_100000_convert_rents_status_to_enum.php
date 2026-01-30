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
     * Convert rents.status from string to enum
     */
    public function up(): void
    {
        // First, ensure all existing values are valid
        DB::statement("UPDATE rents SET status = 'scheduled' WHERE status NOT IN ('scheduled', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show', 'rescheduled', 'active')");
        
        Schema::table('rents', function (Blueprint $table) {
            // Drop existing index if it exists
            $table->dropIndex(['cloth_id', 'status']);
            $table->dropIndex(['appointment_type', 'status']);
        });
        
        // Change column type to enum
        DB::statement("ALTER TABLE rents MODIFY COLUMN status ENUM('scheduled', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show', 'rescheduled', 'active') NOT NULL DEFAULT 'scheduled'");
        
        Schema::table('rents', function (Blueprint $table) {
            // Recreate indexes
            $table->index(['cloth_id', 'status']);
            $table->index(['appointment_type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rents', function (Blueprint $table) {
            $table->dropIndex(['cloth_id', 'status']);
            $table->dropIndex(['appointment_type', 'status']);
        });
        
        DB::statement("ALTER TABLE rents MODIFY COLUMN status VARCHAR(30) NOT NULL DEFAULT 'scheduled'");
        
        Schema::table('rents', function (Blueprint $table) {
            $table->index(['cloth_id', 'status']);
            $table->index(['appointment_type', 'status']);
        });
    }
};

