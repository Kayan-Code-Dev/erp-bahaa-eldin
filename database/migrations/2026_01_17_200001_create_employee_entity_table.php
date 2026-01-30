<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates a polymorphic table for assigning employees to various entity types
     * (branches, workshops, factories). This replaces/supplements the employee_branch table.
     */
    public function up(): void
    {
        Schema::create('employee_entity', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->string('entity_type', 50); // 'branch', 'workshop', 'factory'
            $table->unsignedBigInteger('entity_id');
            $table->boolean('is_primary')->default(false); // Primary work location
            $table->date('assigned_at')->nullable();
            $table->date('unassigned_at')->nullable();
            $table->timestamps();

            // Unique constraint to prevent duplicate assignments
            $table->unique(['employee_id', 'entity_type', 'entity_id'], 'employee_entity_unique');

            // Indexes for efficient queries
            $table->index('employee_id');
            $table->index(['entity_type', 'entity_id']);
            $table->index('is_primary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_entity');
    }
};

