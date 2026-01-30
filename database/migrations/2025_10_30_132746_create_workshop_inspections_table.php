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
        Schema::create('workshop_inspections', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('inspection_employee_id')->nullable()->constrained('employees')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('delivery_employee_id')->nullable()->constrained('employees')->cascadeOnUpdate()->restrictOnDelete();
            $table->enum('status', ['pending', 'under_inspection', 'delivered_to_client', 'returned_to_company'])->default('pending');
            $table->string('invoice_number')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workshop_inspections');
    }
};
