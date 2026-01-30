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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade');
            $table->string('employee_code')->unique();
            $table->foreignId('department_id')->nullable()->constrained('departments')->onDelete('set null');
            $table->foreignId('job_title_id')->nullable()->constrained('job_titles')->onDelete('set null');
            $table->foreignId('manager_id')->nullable()->constrained('employees')->onDelete('set null');
            
            // Employment details
            $table->enum('employment_type', ['full_time', 'part_time', 'contract', 'intern'])->default('full_time');
            $table->enum('employment_status', ['active', 'on_leave', 'suspended', 'terminated'])->default('active');
            $table->date('hire_date');
            $table->date('termination_date')->nullable();
            $table->date('probation_end_date')->nullable();
            
            // Salary structure
            $table->decimal('base_salary', 12, 2)->default(0);
            $table->decimal('transport_allowance', 12, 2)->default(0);
            $table->decimal('housing_allowance', 12, 2)->default(0);
            $table->decimal('other_allowances', 12, 2)->default(0);
            $table->decimal('overtime_rate', 8, 2)->default(1.5); // Multiplier for overtime
            $table->decimal('commission_rate', 5, 2)->default(0); // Percentage of order value
            
            // Leave management
            $table->integer('vacation_days_balance')->default(0);
            $table->integer('vacation_days_used')->default(0);
            $table->integer('annual_vacation_days')->default(21); // Days per year
            
            // Work schedule
            $table->time('work_start_time')->default('09:00:00');
            $table->time('work_end_time')->default('17:00:00');
            $table->unsignedTinyInteger('work_hours_per_day')->default(8);
            $table->unsignedTinyInteger('late_threshold_minutes')->default(15); // Grace period
            
            // Bank details (for salary payment)
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_iban')->nullable();
            
            // Emergency contact
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('emergency_contact_relation')->nullable();
            
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('department_id');
            $table->index('job_title_id');
            $table->index('manager_id');
            $table->index('employment_type');
            $table->index('employment_status');
            $table->index('hire_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};





