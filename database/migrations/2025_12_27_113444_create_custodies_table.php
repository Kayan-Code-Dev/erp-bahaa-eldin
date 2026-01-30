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
        Schema::create('custodies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->enum('type', ['money', 'physical_item', 'document']);
            $table->string('description');
            $table->decimal('value', 10, 2)->nullable();
            $table->enum('status', ['pending', 'returned', 'forfeited'])->default('pending');
            $table->dateTime('returned_at')->nullable();
            $table->string('return_proof_photo')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custodies');
    }
};
