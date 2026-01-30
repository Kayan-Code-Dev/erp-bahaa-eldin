<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates rents/appointments table with all appointment types:
     * - rental_delivery: When dress is given to client
     * - rental_return: When dress is returned by client  
     * - measurement: Client measurement appointment
     * - tailoring_pickup: Pick up tailored dress from factory
     * - tailoring_delivery: Deliver tailored dress to client
     * - fitting: Dress fitting appointment
     * - other: General appointments
     */
    public function up(): void
    {
        Schema::create('rents', function (Blueprint $table) {
            $table->id();
            
            // Client and branch for appointment tracking
            $table->foreignId('client_id')->nullable()->constrained('clients')->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->onDelete('set null');
            
            // Original rental-related columns - now nullable for non-rental appointments
            $table->foreignId('cloth_id')->nullable()->constrained('clothes')->onDelete('cascade');
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('cascade');
            $table->foreignId('cloth_order_id')->nullable()->constrained('cloth_order')->onDelete('cascade');
            
            // Appointment type and title - use string(50) for index compatibility
            $table->string('appointment_type', 50)->default('rental_delivery');
            $table->string('title')->nullable();
            
            // Date and time fields
            $table->date('delivery_date');
            $table->time('appointment_time')->nullable();
            $table->date('return_date')->nullable();
            $table->time('return_time')->nullable();
            $table->integer('days_of_rent')->nullable();
            
            // Status - use string(30) for index compatibility
            $table->string('status', 30)->default('scheduled');
            
            // Notes field
            $table->text('notes')->nullable();
            
            // Reminder tracking
            $table->boolean('reminder_sent')->default(false);
            $table->timestamp('reminder_sent_at')->nullable();
            
            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->onDelete('set null');
            
            $table->timestamps();

            // Indexes for queries
            $table->index('cloth_id');
            $table->index('delivery_date');
            $table->index('return_date');
            $table->index(['cloth_id', 'status']);
            $table->index('appointment_type');
            $table->index('client_id');
            $table->index('branch_id');
            $table->index(['appointment_type', 'status']);
            $table->index(['delivery_date', 'appointment_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rents');
    }
};
