<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Cashbox represents a cash register/drawer for a branch.
     * Each branch has one cashbox. Balance is calculated from transactions.
     * CRITICAL: Balance must never go negative - this is enforced in the TransactionService.
     */
    public function up(): void
    {
        Schema::create('cashboxes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('branch_id')->unique()->constrained('branches')->onDelete('cascade');
            $table->decimal('initial_balance', 15, 2)->default(0.00); // Starting balance when cashbox was created
            $table->decimal('current_balance', 15, 2)->default(0.00); // Cached balance (updated on each transaction)
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cashboxes');
    }
};






