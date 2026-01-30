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
        Schema::create('cloth_order', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('cloth_id')->constrained('clothes')->onDelete('cascade');
            $table->decimal('price', 10, 2);
            $table->enum('type', ['buy', 'rent', 'tailoring']);
            $table->integer('days_of_rent')->nullable();
            $table->dateTime('occasion_datetime')->nullable();
            $table->date('delivery_date')->nullable();
            $table->boolean('returnable')->default(true)->nullable(false);
            $table->enum('status', ['created', 'partially_paid', 'paid', 'delivered', 'finished', 'canceled', 'rented'])->default('created');
            $table->text('notes')->nullable();
            $table->enum('discount_type', ['none', 'percentage', 'fixed'])->nullable();
            $table->decimal('discount_value', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cloth_order');
    }
};
