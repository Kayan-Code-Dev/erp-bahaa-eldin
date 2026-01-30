<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates the pivot table for many-to-many relationship between job_titles and roles.
     * This allows JobTitles to have assigned roles, which contribute to user permissions.
     */
    public function up(): void
    {
        Schema::create('job_title_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_title_id')->constrained('job_titles')->onDelete('cascade');
            $table->foreignId('role_id')->constrained('roles')->onDelete('cascade');
            $table->timestamps();

            // Unique constraint to prevent duplicate assignments
            $table->unique(['job_title_id', 'role_id']);

            // Indexes for faster queries
            $table->index('job_title_id');
            $table->index('role_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_title_role');
    }
};

