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
        Schema::create('branch_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100); // اسم الوظيفة
            $table->string('code', 20)->nullable(); // كود الوظيفة اختياري
            $table->text('description')->nullable(); // وصف الوظيفة
            $table->boolean('active')->default(true); // حالة الوظيفة
            $table->foreignId('department_id')->constrained('departments')->onDelete('cascade'); // مرتبط بالقسم
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade'); // مرتبط بالفرع
            $table->timestamps();
            $table->unique(['branch_id', 'department_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branch_jobs');
    }
};
