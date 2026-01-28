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
        Schema::create('tailoring_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->dateTime('visit_date')->nullable();
            $table->dateTime('event_date')->nullable();
            $table->string('model_name');
            $table->string('fabric_preference')->nullable();
            $table->json('measurements')->nullable();
            $table->date('delivery_date')->nullable();
            $table->integer('quantity')->default(0);
            $table->text('source')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tailoring_orders');
    }
};
