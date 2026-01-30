<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add statistics tracking fields to factories table.
     * These are cached/computed values that are periodically updated.
     */
    public function up(): void
    {
        Schema::table('factories', function (Blueprint $table) {
            // Current orders being processed
            $table->unsignedInteger('current_orders_count')->default(0)->after('address_id');
            
            // Total orders completed all time
            $table->unsignedInteger('total_orders_completed')->default(0)->after('current_orders_count');
            
            // Average completion time in days
            $table->decimal('average_completion_days', 6, 2)->default(0)->after('total_orders_completed');
            
            // Overall quality rating (1-5 scale, calculated from evaluations)
            $table->decimal('quality_rating', 3, 2)->default(0)->after('average_completion_days');
            
            // On-time delivery rate (percentage)
            $table->decimal('on_time_rate', 5, 2)->default(0)->after('quality_rating');
            
            // Total evaluations received
            $table->unsignedInteger('total_evaluations')->default(0)->after('on_time_rate');
            
            // Factory status
            $table->string('factory_status')->default('active')->after('total_evaluations');
            
            // Maximum concurrent orders the factory can handle
            $table->unsignedInteger('max_capacity')->nullable()->after('factory_status');
            
            // Contact information
            $table->string('contact_person')->nullable()->after('max_capacity');
            $table->string('contact_phone')->nullable()->after('contact_person');
            $table->string('contact_email')->nullable()->after('contact_phone');
            
            // Notes
            $table->text('notes')->nullable()->after('contact_email');
            
            // Last statistics calculation timestamp
            $table->timestamp('stats_calculated_at')->nullable()->after('notes');
            
            // Indexes
            $table->index('quality_rating');
            $table->index('on_time_rate');
            $table->index('factory_status');
            $table->index('current_orders_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('factories', function (Blueprint $table) {
            $table->dropIndex(['quality_rating']);
            $table->dropIndex(['on_time_rate']);
            $table->dropIndex(['factory_status']);
            $table->dropIndex(['current_orders_count']);
            
            $table->dropColumn([
                'current_orders_count',
                'total_orders_completed',
                'average_completion_days',
                'quality_rating',
                'on_time_rate',
                'total_evaluations',
                'factory_status',
                'max_capacity',
                'contact_person',
                'contact_phone',
                'contact_email',
                'notes',
                'stats_calculated_at',
            ]);
        });
    }
};





