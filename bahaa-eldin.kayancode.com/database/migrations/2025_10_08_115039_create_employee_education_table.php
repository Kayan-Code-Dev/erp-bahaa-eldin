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
        Schema::create('employee_education', function (Blueprint $table) {
            $table->id();
            // ربط الموظف
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('degree_name', 100)->nullable(); // منطوق المؤهل
            $table->string('university', 150)->nullable(); // الجامعة
            $table->string('faculty', 150)->nullable(); // الكلية
            $table->year('graduation_year')->nullable(); // سنة التخرج
            $table->string('specialization', 150)->nullable(); // التخصص
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_education');
    }
};
