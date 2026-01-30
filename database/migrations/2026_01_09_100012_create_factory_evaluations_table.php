<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Create factory evaluations table for rating factory performance.
     * Each evaluation is linked to a specific order completion.
     */
    public function up(): void
    {
        Schema::create('factory_evaluations', function (Blueprint $table) {
            $table->id();
            
            // Factory being evaluated
            $table->foreignId('factory_id')->constrained('factories')->onDelete('cascade');
            
            // Order this evaluation is for (optional - can evaluate factory generally)
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('set null');
            
            // Quality rating (1-5 scale)
            $table->unsignedTinyInteger('quality_rating')->comment('1-5 rating scale');
            
            // Actual days taken to complete
            $table->unsignedInteger('completion_days')->nullable();
            
            // Expected days (from the order)
            $table->unsignedInteger('expected_days')->nullable();
            
            // Was it delivered on time?
            $table->boolean('on_time')->default(true);
            
            // Individual rating categories (1-5 each)
            $table->unsignedTinyInteger('craftsmanship_rating')->nullable()->comment('1-5 quality of work');
            $table->unsignedTinyInteger('communication_rating')->nullable()->comment('1-5 communication quality');
            $table->unsignedTinyInteger('packaging_rating')->nullable()->comment('1-5 packaging quality');
            
            // Detailed feedback
            $table->text('notes')->nullable();
            $table->text('issues_found')->nullable();
            $table->text('positive_feedback')->nullable();
            
            // Who created this evaluation
            $table->foreignId('evaluated_by')->constrained('users')->onDelete('cascade');
            
            // When the evaluation was done
            $table->timestamp('evaluated_at')->useCurrent();
            
            $table->timestamps();
            
            // Indexes
            $table->index('factory_id');
            $table->index('order_id');
            $table->index('quality_rating');
            $table->index('on_time');
            $table->index('evaluated_at');
            $table->index(['factory_id', 'evaluated_at']);
            
            // Unique constraint - one evaluation per order
            $table->unique(['factory_id', 'order_id'], 'unique_factory_order_evaluation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('factory_evaluations');
    }
};





