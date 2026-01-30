<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Create a log table for tailoring stage transitions.
     * This provides an audit trail of all stage changes for tailoring orders.
     */
    public function up(): void
    {
        Schema::create('tailoring_stage_logs', function (Blueprint $table) {
            $table->id();
            
            // Order this log belongs to
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            
            // The stage transition
            $table->string('from_stage')->nullable(); // Null for initial stage
            $table->string('to_stage');
            
            // Who made the change
            $table->foreignId('changed_by')->constrained('users')->onDelete('cascade');
            
            // Optional notes about the transition
            $table->text('notes')->nullable();
            
            // Metadata
            $table->json('metadata')->nullable(); // For any additional context
            
            // Timestamp (no updated_at since logs are immutable)
            $table->timestamp('created_at')->useCurrent();
            
            // Indexes
            $table->index('order_id');
            $table->index('from_stage');
            $table->index('to_stage');
            $table->index('changed_by');
            $table->index('created_at');
            $table->index(['order_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tailoring_stage_logs');
    }
};





