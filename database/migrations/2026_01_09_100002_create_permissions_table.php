<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates the permissions table for RBAC system.
     * Permissions follow the format: module.action (e.g., orders.create, clients.view)
     */
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g., 'orders.create', 'clients.view'
            $table->string('display_name'); // Human-readable name
            $table->text('description')->nullable();
            $table->string('module'); // e.g., 'orders', 'clients', 'payments'
            $table->string('action'); // e.g., 'create', 'view', 'update', 'delete'
            $table->timestamps();

            // Index for faster queries by module
            $table->index('module');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};






