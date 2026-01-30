<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add factory-related fields to cloth_order pivot table for tailoring items.
     * These fields track the factory workflow at the item level.
     */
    public function up(): void
    {
        Schema::table('cloth_order', function (Blueprint $table) {
            $table->enum('factory_status', [
                'new',
                'pending_factory_approval',
                'rejected',
                'accepted',
                'in_progress',
                'ready_for_delivery',
                'delivered_to_atelier',
                'closed'
            ])->nullable()->after('status');
            
            $table->text('factory_rejection_reason')->nullable()->after('factory_status');
            $table->timestamp('factory_accepted_at')->nullable()->after('factory_rejection_reason');
            $table->timestamp('factory_rejected_at')->nullable()->after('factory_accepted_at');
            $table->date('factory_expected_delivery_date')->nullable()->after('factory_rejected_at');
            $table->timestamp('factory_delivered_at')->nullable()->after('factory_expected_delivery_date');
            $table->text('factory_notes')->nullable()->after('factory_delivered_at');

            $table->index('factory_status');
            $table->index('factory_expected_delivery_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cloth_order', function (Blueprint $table) {
            $table->dropIndex(['factory_status']);
            $table->dropIndex(['factory_expected_delivery_date']);
            
            $table->dropColumn([
                'factory_status',
                'factory_rejection_reason',
                'factory_accepted_at',
                'factory_rejected_at',
                'factory_expected_delivery_date',
                'factory_delivered_at',
                'factory_notes',
            ]);
        });
    }
};
