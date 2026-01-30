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
        Schema::create('rent_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('sub_category_id')->nullable()->constrained('sub_categories')->nullOnDelete(); // الفئة الفرعية (اختيارية)
            $table->string('model_name');
            $table->integer('rental_duration');
            $table->json('measurements')->nullable();
            $table->date('delivery_date')->nullable();
            $table->date('event_date')->nullable();
            $table->string('source')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rent_orders');
    }
};
