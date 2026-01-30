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
        Schema::create('deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->enum('type', ['absence', 'late', 'penalty', 'loan_repayment', 'advance_repayment', 'other']);
            $table->string('reason');
            $table->text('description')->nullable();
            $table->decimal('amount', 12, 2);
            $table->date('date');
            $table->string('period')->nullable(); // e.g., '2026-01' for monthly deductions
            $table->unsignedBigInteger('payroll_id')->nullable(); // Foreign key added after payrolls table is created
            $table->boolean('is_applied')->default(false); // Whether it's been applied to a payroll
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('employee_id');
            $table->index('type');
            $table->index('date');
            $table->index('period');
            $table->index('is_applied');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deductions');
    }
};

