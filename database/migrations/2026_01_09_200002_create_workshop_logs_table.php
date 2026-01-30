<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates workshop_logs table to track all cloth operations in workshops
     * including receiving, status changes, and returns
     */
    public function up(): void
    {
        Schema::create('workshop_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained('workshops')->onDelete('cascade');
            $table->foreignId('cloth_id')->constrained('clothes')->onDelete('cascade');
            $table->foreignId('transfer_id')->nullable()->constrained('transfers')->onDelete('set null');
            
            // Action type: received, status_changed, returned
            $table->enum('action', ['received', 'status_changed', 'returned']);
            
            // Cloth status in workshop: received, processing, ready_for_delivery
            $table->enum('cloth_status', ['received', 'processing', 'ready_for_delivery'])->nullable();
            
            // Optional notes from workshop staff
            $table->text('notes')->nullable();
            
            // Timestamps for tracking receive and return dates
            $table->timestamp('received_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            
            // Who performed the action
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            
            $table->timestamps();
            
            // Indexes for common queries
            $table->index(['workshop_id', 'cloth_id']);
            $table->index(['cloth_id', 'action']);
            $table->index('cloth_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workshop_logs');
    }
};





