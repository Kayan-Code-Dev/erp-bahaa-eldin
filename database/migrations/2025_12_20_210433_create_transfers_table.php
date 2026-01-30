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
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->string('from_entity_type', 50);
            $table->unsignedBigInteger('from_entity_id');
            $table->string('to_entity_type', 50);
            $table->unsignedBigInteger('to_entity_id');
            $table->date('transfer_date');
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'partially_pending', 'partially_approved', 'approved', 'rejected'])->default('pending');
            $table->timestamps();

            $table->index(['from_entity_type', 'from_entity_id']);
            $table->index(['to_entity_type', 'to_entity_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
