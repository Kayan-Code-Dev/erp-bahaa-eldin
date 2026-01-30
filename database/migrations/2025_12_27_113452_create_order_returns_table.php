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
        Schema::create('order_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->integer('step')->default(1)->comment('Current step in workflow (1-5)');
            $table->foreignId('returned_cloth_id')->nullable()->constrained('clothes')->onDelete('set null');
            $table->enum('return_entity_type', ['branch', 'workshop', 'factory'])->nullable();
            $table->unsignedBigInteger('return_entity_id')->nullable();
            $table->enum('cloth_status_on_return', ['damaged', 'burned', 'scratched', 'ready_for_rent', 'rented', 'repairing', 'die'])->nullable();
            $table->decimal('fees_amount', 10, 2)->default(0);
            $table->boolean('fees_paid')->default(false);
            $table->dateTime('fees_payment_date')->nullable();
            $table->dateTime('return_date')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('cloth_return_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('cloth_id')->constrained('clothes')->onDelete('cascade');
            $table->string('photo_path');
            $table->enum('photo_type', ['return_photo'])->default('return_photo');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cloth_return_photos');
        Schema::dropIfExists('order_returns');
    }
};
