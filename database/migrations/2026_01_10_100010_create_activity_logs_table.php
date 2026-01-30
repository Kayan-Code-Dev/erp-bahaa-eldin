<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('action', 50); // created, updated, deleted, restored, login, logout, etc.
            $table->string('entity_type', 100)->nullable(); // Full model class name
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('entity_name')->nullable(); // Human-readable entity name for display
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('changed_fields')->nullable(); // List of fields that changed
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('url')->nullable();
            $table->string('method')->nullable(); // GET, POST, PUT, DELETE
            $table->foreignId('branch_id')->nullable()->constrained('branches')->onDelete('set null');
            $table->text('description')->nullable(); // Human-readable description
            $table->json('metadata')->nullable(); // Additional context data
            $table->timestamps();

            $table->index('user_id');
            $table->index(['entity_type', 'entity_id']);
            $table->index('action');
            $table->index('created_at');
            $table->index('branch_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};





