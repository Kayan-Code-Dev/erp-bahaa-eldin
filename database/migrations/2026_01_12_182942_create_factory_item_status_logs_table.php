<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Create factory_item_status_logs table to track status changes at item level.
     * Provides audit trail for factory workflow on tailoring items.
     */
    public function up(): void
    {
        Schema::create('factory_item_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cloth_order_id')->constrained('cloth_order')->onDelete('cascade');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('changed_by')->constrained('users')->onDelete('cascade');
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('cloth_order_id');
            $table->index('from_status');
            $table->index('to_status');
            $table->index('changed_by');
            $table->index('created_at');
            $table->index(['cloth_order_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('factory_item_status_logs');
    }
};
