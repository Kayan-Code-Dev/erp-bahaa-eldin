<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Pivot table to link supplier orders with clothes
     */
    public function up(): void
    {
        Schema::create('supplier_order_clothes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_order_id')->constrained('supplier_orders')->onDelete('cascade');
            $table->foreignId('cloth_id')->constrained('clothes')->onDelete('cascade');
            $table->decimal('price', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('supplier_order_id');
            $table->index('cloth_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_order_clothes');
    }
};

