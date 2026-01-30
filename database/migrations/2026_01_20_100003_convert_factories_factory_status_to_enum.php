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
     * Convert factories.factory_status from string to enum
     */
    public function up(): void
    {
        // First, ensure all existing values are valid
        DB::statement("UPDATE factories SET factory_status = 'active' WHERE factory_status NOT IN ('active', 'inactive', 'suspended', 'closed') OR factory_status IS NULL OR factory_status = ''");
        
        Schema::table('factories', function (Blueprint $table) {
            // Drop existing index
            $table->dropIndex(['factory_status']);
        });
        
        // Change column type to enum
        DB::statement("ALTER TABLE factories MODIFY COLUMN factory_status ENUM('active', 'inactive', 'suspended', 'closed') NOT NULL DEFAULT 'active'");
        
        Schema::table('factories', function (Blueprint $table) {
            // Recreate index
            $table->index('factory_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('factories', function (Blueprint $table) {
            $table->dropIndex(['factory_status']);
        });
        
        DB::statement("ALTER TABLE factories MODIFY COLUMN factory_status VARCHAR(255) NOT NULL DEFAULT 'active'");
        
        Schema::table('factories', function (Blueprint $table) {
            $table->index('factory_status');
        });
    }
};

