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
        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tailoring_order_id')->constrained('tailoring_orders')->onDelete('cascade');
            $table->string('production_code')->unique(); // رقم أمر الإنتاج الداخلي
            $table->date('start_date')->nullable(); //تاريخ البدء
            $table->date('expected_finish_date')->nullable(); //تاريخ الانتهاء المتوقع
            $table->date('actual_finish_date')->nullable(); //تاريخ الانتهاء الفعلي
            $table->enum('status', ['pending', 'in_progress', 'paused', 'completed', 'canceled'])->default('pending'); //معلق', 'قيد التقدم', 'متوقف مؤقتًا', 'مكتمل', 'ملغى
            $table->string('production_line')->nullable(); // خط الإنتاج أو القسم
            $table->integer('produced_quantity')->default(0); //الكمية المنتجة
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_orders');
    }
};
