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
        Schema::create('employee_contacts', function (Blueprint $table) {
            $table->id();
            // ربط الموظف
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnUpdate()->cascadeOnDelete();
            // العنوان ووسائل التواصل
            $table->string('neighborhood', 100)->nullable(); // الحي/القرية
            $table->string('province', 100)->nullable(); // المحافظة
            $table->string('address', 255)->nullable(); // العنوان التفصيلي
            $table->string('home_phone_1', 20)->nullable(); // هاتف منزلي 1
            $table->string('home_phone_2', 20)->nullable(); // هاتف منزلي 2
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_contacts');
    }
};
