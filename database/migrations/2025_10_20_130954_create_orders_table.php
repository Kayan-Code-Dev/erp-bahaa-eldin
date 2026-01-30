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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('order_number')->unique();
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->morphs('creator'); // بدلاً من branch_id
            $table->enum('order_type', ['tailoring', 'purchase', 'rent']);
            $table->enum('status', ['pending', 'processing', 'done', 'canceled'])->default('pending');
            $table->date('delivery_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
