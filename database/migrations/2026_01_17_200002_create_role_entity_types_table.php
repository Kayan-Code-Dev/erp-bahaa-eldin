<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates a table to restrict roles to specific entity types.
     * If a role has no entries in this table, it applies to all entity types.
     * If it has entries, it only applies to those entity types.
     */
    public function up(): void
    {
        Schema::create('role_entity_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->onDelete('cascade');
            $table->enum('entity_type', ['branch', 'workshop', 'factory']);
            $table->timestamps();

            // Unique constraint to prevent duplicate entries
            $table->unique(['role_id', 'entity_type']);

            // Index for efficient queries
            $table->index('role_id');
            $table->index('entity_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_entity_types');
    }
};

