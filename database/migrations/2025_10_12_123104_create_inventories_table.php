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
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100); // اسم المنتج
            $table->string('code', 50)->unique(); // كود المنتج
            $table->foreignId('subCategories_id')->nullable()->constrained('sub_categories')->nullOnDelete(); // الفئة (قابل تكون فارغة)
            $table->decimal('price', 10, 2)->nullable(); // السعر
            $table->enum('type', ['raw', 'product'])->default('raw'); // نوع المنتج [منتج أو خام]
            $table->text('notes')->nullable(); // ملاحظات
            $table->decimal('quantity', 10, 2)->default(0); // الكمية
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete(); // تابع لأي فرع
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
