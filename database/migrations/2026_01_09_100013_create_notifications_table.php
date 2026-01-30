<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Create notifications table for user alerts and reminders.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            
            // Target user (nullable for broadcast notifications)
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            
            // Notification type for categorization and filtering
            $table->string('type', 50); // appointment_reminder, overdue_return, payment_due, order_status, system
            
            // Content
            $table->string('title');
            $table->text('message');
            
            // Reference to related entity (polymorphic)
            $table->string('reference_type', 100)->nullable(); // Order, Rent, Payment, etc.
            $table->unsignedBigInteger('reference_id')->nullable();
            
            // Priority level
            $table->string('priority', 20)->default('normal'); // low, normal, high, urgent
            
            // Status tracking
            $table->timestamp('read_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            
            // Action URL (optional - where to redirect when clicked)
            $table->string('action_url')->nullable();
            
            // Metadata for additional context
            $table->json('metadata')->nullable();
            
            // Scheduling
            $table->timestamp('scheduled_for')->nullable(); // For future notifications
            $table->timestamp('sent_at')->nullable(); // When actually delivered
            
            $table->timestamps();
            
            // Indexes
            $table->index('user_id');
            $table->index('type');
            $table->index('priority');
            $table->index('read_at');
            $table->index('scheduled_for');
            $table->index(['reference_type', 'reference_id']);
            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'type', 'read_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};





