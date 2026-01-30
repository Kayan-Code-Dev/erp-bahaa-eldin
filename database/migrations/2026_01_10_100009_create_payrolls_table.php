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
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->string('period'); // e.g., '2026-01'
            $table->date('period_start');
            $table->date('period_end');
            
            // Salary components
            $table->decimal('base_salary', 12, 2)->default(0);
            $table->decimal('transport_allowance', 12, 2)->default(0);
            $table->decimal('housing_allowance', 12, 2)->default(0);
            $table->decimal('other_allowances', 12, 2)->default(0);
            $table->decimal('total_allowances', 12, 2)->default(0);
            
            // Overtime
            $table->decimal('overtime_hours', 8, 2)->default(0);
            $table->decimal('overtime_rate', 8, 2)->default(1.5);
            $table->decimal('overtime_amount', 12, 2)->default(0);
            
            // Commission
            $table->integer('orders_count')->default(0);
            $table->decimal('orders_total', 12, 2)->default(0);
            $table->decimal('commission_rate', 5, 2)->default(0);
            $table->decimal('commission_amount', 12, 2)->default(0);
            
            // Attendance summary
            $table->integer('working_days')->default(0);
            $table->integer('days_present')->default(0);
            $table->integer('days_absent')->default(0);
            $table->integer('days_late')->default(0);
            $table->integer('leave_days')->default(0);
            
            // Deductions
            $table->decimal('absence_deductions', 12, 2)->default(0);
            $table->decimal('late_deductions', 12, 2)->default(0);
            $table->decimal('penalty_deductions', 12, 2)->default(0);
            $table->decimal('other_deductions', 12, 2)->default(0);
            $table->decimal('total_deductions', 12, 2)->default(0);
            
            // Totals
            $table->decimal('gross_salary', 12, 2)->default(0);
            $table->decimal('net_salary', 12, 2)->default(0);
            
            // Status workflow
            $table->enum('status', ['draft', 'pending', 'approved', 'paid', 'cancelled'])->default('draft');
            
            // Audit fields
            $table->foreignId('generated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('paid_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->onDelete('set null');
            
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            
            // Payment details
            $table->foreignId('cashbox_id')->nullable()->constrained('cashboxes')->onDelete('set null');
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->onDelete('set null');
            $table->string('payment_method')->nullable(); // cash, bank_transfer
            $table->string('payment_reference')->nullable();
            
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['employee_id', 'period']);
            $table->index('employee_id');
            $table->index('period');
            $table->index('status');
            $table->index('paid_at');
        });

        // Payroll items - detailed breakdown
        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained('payrolls')->onDelete('cascade');
            $table->enum('type', ['earning', 'deduction']);
            $table->string('category'); // base_salary, transport, housing, overtime, commission, absence, late, penalty
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->decimal('quantity', 8, 2)->default(1); // e.g., overtime hours, days absent
            $table->decimal('rate', 12, 2)->nullable(); // e.g., hourly rate
            $table->json('metadata')->nullable(); // Additional details
            $table->timestamps();

            $table->index('payroll_id');
            $table->index('type');
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payrolls');
    }
};





