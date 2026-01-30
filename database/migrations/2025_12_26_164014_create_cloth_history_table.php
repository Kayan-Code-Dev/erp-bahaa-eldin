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
        Schema::create('cloth_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cloth_id')->constrained('clothes')->onDelete('cascade');
            $table->string('action'); // created, transferred, ordered, returned, status_changed
            $table->string('entity_type')->nullable(); // branch, workshop, factory
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->foreignId('transfer_id')->nullable()->constrained('transfers')->onDelete('set null');
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('set null');
            $table->string('status')->nullable(); // for status_changed action
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cloth_history');
    }
};
