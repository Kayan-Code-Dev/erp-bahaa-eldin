<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add tailoring stage tracking to orders for tailoring-type orders.
     * This allows tracking the progress of tailoring orders through the factory.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Tailoring stage - tracks current stage in the tailoring workflow
            $table->string('tailoring_stage')->nullable()->after('status');
            
            // When the stage was last changed
            $table->timestamp('tailoring_stage_changed_at')->nullable()->after('tailoring_stage');
            
            // Expected completion date from factory
            $table->date('expected_completion_date')->nullable()->after('tailoring_stage_changed_at');
            
            // Actual completion date (when marked ready_from_factory)
            $table->date('actual_completion_date')->nullable()->after('expected_completion_date');
            
            // Factory assigned to this tailoring order
            $table->foreignId('assigned_factory_id')->nullable()->after('actual_completion_date')
                  ->constrained('factories')->onDelete('set null');
            
            // Date when sent to factory
            $table->date('sent_to_factory_date')->nullable()->after('assigned_factory_id');
            
            // Date when received back from factory
            $table->date('received_from_factory_date')->nullable()->after('sent_to_factory_date');
            
            // Factory-specific notes
            $table->text('factory_notes')->nullable()->after('received_from_factory_date');
            
            // Priority level for factory orders
            $table->string('priority')->default('normal')->after('factory_notes');
            
            // Indexes for queries
            $table->index('tailoring_stage');
            $table->index('assigned_factory_id');
            $table->index('expected_completion_date');
            $table->index(['tailoring_stage', 'assigned_factory_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['assigned_factory_id']);
            
            $table->dropIndex(['tailoring_stage']);
            $table->dropIndex(['assigned_factory_id']);
            $table->dropIndex(['expected_completion_date']);
            $table->dropIndex(['tailoring_stage', 'assigned_factory_id']);
            
            $table->dropColumn([
                'tailoring_stage',
                'tailoring_stage_changed_at',
                'expected_completion_date',
                'actual_completion_date',
                'assigned_factory_id',
                'sent_to_factory_date',
                'received_from_factory_date',
                'factory_notes',
                'priority',
            ]);
        });
    }
};





