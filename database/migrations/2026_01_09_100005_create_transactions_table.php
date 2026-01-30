<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * CRITICAL: Transactions are IMMUTABLE!
     * - No updates allowed (create a reversal transaction instead)
     * - No deletes allowed (create a reversal transaction instead)
     * - This ensures perfect audit trail and cashbox integrity
     * 
     * Transaction types:
     * - income: Money coming IN to cashbox (payments, custody deposits)
     * - expense: Money going OUT of cashbox (refunds, expenses, custody returns)
     * - reversal: Corrects a previous transaction (must reference original)
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cashbox_id')->constrained('cashboxes')->onDelete('restrict'); // Prevent cashbox deletion if has transactions
            $table->enum('type', ['income', 'expense', 'reversal']);
            $table->decimal('amount', 15, 2); // Always positive; type determines direction
            $table->decimal('balance_after', 15, 2); // Cashbox balance after this transaction (for audit)
            $table->string('category', 50); // payment, custody_deposit, custody_return, expense, reversal, etc.
            $table->text('description');
            
            // Reference to what caused this transaction (polymorphic)
            $table->string('reference_type', 100)->nullable(); // Payment, Custody, Expense, Order, etc.
            $table->unsignedBigInteger('reference_id')->nullable();
            
            // For reversals - link to the original transaction being reversed
            $table->foreignId('reversed_transaction_id')->nullable()->constrained('transactions')->onDelete('restrict');
            
            // Who created this transaction
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            
            // Metadata (JSON) for additional context
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            // NO soft deletes - transactions are permanent!
            // NO updated_at in practice - we don't allow updates
            
            // Indexes for common queries
            $table->index(['cashbox_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('category');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};






