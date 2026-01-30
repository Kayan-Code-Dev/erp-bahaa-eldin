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
            $table->uuid('uuid')->unique();
            // البيانات الأساسية
            $table->string('full_name', 255);
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('phone', 20)->unique();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('city_id')->constrained('cities')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('national_id', 50)->unique();
            $table->foreignId('branch_job_id')->nullable()->constrained('branch_jobs')->cascadeOnUpdate()->restrictOnDelete();
            $table->enum('status', ['active', 'inactive', 'terminated'])->default('inactive');
            // البيانات الشخصية الأساسية
            $table->enum('gender', ['male', 'female'])->nullable(); // الجنس
            $table->date('birth_date')->nullable(); // تاريخ الميلاد
            $table->string('passport_number', 50)->nullable()->unique(); // رقم جواز السفر
            $table->string('nationality', 50)->nullable(); // الجنسية
            $table->string('religion', 50)->nullable(); // الديانة
            $table->enum('marital_status', ['single', 'married', 'divorced', 'widowed'])->nullable(); // الحالة الاجتماعية
            $table->enum('military_status', ['exempted', 'completed', 'postponed'])->nullable(); // حالة التجنيد
            $table->string('insurance_number', 50)->nullable(); // الرقم التأميني
            $table->softDeletes();
            $table->timestamps();
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
