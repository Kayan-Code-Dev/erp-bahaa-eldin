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
     * Convert orders.priority from string to enum
     */
    public function up(): void
    {
        // First, ensure all existing values are valid
        DB::statement("UPDATE orders SET priority = 'normal' WHERE priority NOT IN ('low', 'normal', 'high', 'urgent') OR priority IS NULL OR priority = ''");
        
        // Change column type to enum
        DB::statement("ALTER TABLE orders MODIFY COLUMN priority ENUM('low', 'normal', 'high', 'urgent') NOT NULL DEFAULT 'normal'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN priority VARCHAR(255) NOT NULL DEFAULT 'normal'");
    }
};

