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
        Schema::create('clothes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('cloth_type_id')->constrained('cloth_types')->onDelete('cascade');
            $table->string('breast_size')->nullable();
            $table->string('waist_size')->nullable();
            $table->string('sleeve_size')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['damaged', 'burned', 'scratched', 'ready_for_rent', 'rented', 'repairing', 'die', 'sold'])->default('ready_for_rent');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clothes');
    }
};
