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
        Schema::create('workshop_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_inspection_id')->constrained('workshop_inspections')->cascadeOnDelete();
            $table->string('received_by'); // اسم استلم
            $table->timestamp('received_at')->useCurrent(); // تاريخ ووقت الاستلام
            $table->date('rental_start_date')->nullable(); // مدة الإيجار من تاريخ
            $table->date('rental_end_date')->nullable();   // حتى يوم
            $table->text('notes')->nullable(); // ملاحظات إضافية (اختياري)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workshop_receipts');
    }
};
