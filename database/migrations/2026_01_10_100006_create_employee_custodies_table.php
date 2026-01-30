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
        Schema::create('employee_custodies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->enum('type', ['laptop', 'phone', 'tablet', 'keys', 'tools', 'uniform', 'vehicle', 'other']);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('asset_tag')->nullable();
            $table->decimal('value', 12, 2)->default(0);
            $table->enum('condition_on_assignment', ['new', 'good', 'fair', 'poor']);
            $table->enum('condition_on_return', ['new', 'good', 'fair', 'poor', 'damaged', 'lost'])->nullable();
            $table->enum('status', ['assigned', 'returned', 'damaged', 'lost'])->default('assigned');
            $table->date('assigned_date');
            $table->date('expected_return_date')->nullable();
            $table->date('returned_date')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('returned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->text('return_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('employee_id');
            $table->index('type');
            $table->index('status');
            $table->index('assigned_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_custodies');
    }
};





