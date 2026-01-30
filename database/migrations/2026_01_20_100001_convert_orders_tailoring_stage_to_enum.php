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
     * Convert orders.tailoring_stage from string to enum
     */
    public function up(): void
    {
        // First, ensure all existing values are valid or set to NULL
        DB::statement("UPDATE orders SET tailoring_stage = NULL WHERE tailoring_stage NOT IN ('received', 'sent_to_factory', 'in_production', 'ready_from_factory', 'ready_for_customer', 'delivered') OR tailoring_stage = ''");
        
        Schema::table('orders', function (Blueprint $table) {
            // Drop existing indexes
            $table->dropIndex(['tailoring_stage']);
            $table->dropIndex(['tailoring_stage', 'assigned_factory_id']);
        });
        
        // Change column type to enum
        DB::statement("ALTER TABLE orders MODIFY COLUMN tailoring_stage ENUM('received', 'sent_to_factory', 'in_production', 'ready_from_factory', 'ready_for_customer', 'delivered') NULL");
        
        Schema::table('orders', function (Blueprint $table) {
            // Recreate indexes
            $table->index('tailoring_stage');
            $table->index(['tailoring_stage', 'assigned_factory_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['tailoring_stage']);
            $table->dropIndex(['tailoring_stage', 'assigned_factory_id']);
        });
        
        DB::statement("ALTER TABLE orders MODIFY COLUMN tailoring_stage VARCHAR(255) NULL");
        
        Schema::table('orders', function (Blueprint $table) {
            $table->index('tailoring_stage');
            $table->index(['tailoring_stage', 'assigned_factory_id']);
        });
    }
};

